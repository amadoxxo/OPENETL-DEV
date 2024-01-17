import { Injectable } from '@angular/core';
import { BaseService } from '../../../core/base.service';
import { Observable, map } from 'rxjs';
import { HttpClient, HttpParams } from '@angular/common/http';

@Injectable()
export class GestionDocumentosService extends BaseService {
    /**
     * Constructor del servicio.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene una lista paginada de registros.
     *
     * @param {object} params Parámetros de la petición
     * @param {string} paginador Permite establecer si se debe enviar el link para página siguiente o anterior
     * @param {string} linkAnterior Link a la página anterior
     * @param {string} linkSiguiente Link a la página siguiente
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    listarDocumentos(params: object, paginador: string, linkAnterior: string, linkSiguiente: string): Observable<any> {
        let complementoUrl = '';
        if(paginador === 'anterior')
            complementoUrl = '?cursor=' + linkAnterior;
        else if(paginador === 'siguiente')
            complementoUrl = '?cursor=' + linkSiguiente;

        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/gestion-documentos/lista-etapas${complementoUrl}`,
            params,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Obtiene una lista de centros de operación activos.
     *
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    getCentrosOperacion(): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/configuracion/centros-operacion/search-registros`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de centros de costo activos.
     *
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    getCentrosCosto(): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/configuracion/centros-costo/search-registros`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de centros de costo activos.
     *
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    getCausalesDevolucion(): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/configuracion/causales-devolucion/search-registros`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Envía a gestionar las etapas por la acción Gestionar Fe/Ds.
     *
     * @param {object} params Parámetros de la petición
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    gestionarEtapasFeDs(params: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/gestion-documentos/gestionar-etapas`,
            params,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Envía a asignar el centro de operación a uno o más documentos.
     *
     * @param {object} params Parámetros de la petición
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    enviarAsignarCentroOperacion(params: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/gestion-documentos/asignar-centro-operaciones`,
            params,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Envía a asignar el centro de costo a uno o más documentos.
     *
     * @param {object} params Parámetros de la petición
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    enviarAsignarCentroCosto(params: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/gestion-documentos/asignar-centro-costo`,
            params,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Envía a la siguiente etapa a uno o más documentos.
     *
     * @param {object} params Parámetros de la petición
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    enviarSiguienteEtapa(params: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/gestion-documentos/siguiente-etapa`,
            params,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Envía la petición para asignar los datos contabilizado.
     *
     * @param {object} params Parámetros de la petición
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    enviarDatosContabilizado(params: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/gestion-documentos/datos-contabilizado`,
            params,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Obtiene una lista paginada de registros.
     *
     * @param {object} paramsObject Parámetros de la petición
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    getEmisoresSearch(paramsObject: object): Observable<any> {
        let params = new HttpParams();
        for (const key in paramsObject) {
            if (paramsObject.hasOwnProperty(key)) {
                params = params.append(key, paramsObject[key]);
            }
        }

        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/gestion-documentos/search-emisores`,
            { headers: this.getHeaders(), params}
        ).pipe( map( (res: any) => res.data));
    }

    /**
     * Obtiene la información del detalle de todas las etapas.
     *
     * @param {number} gdoId Id del documento en gestión
     * @return {Observable<any>}
     * @memberof GestionDocumentosService
     */
    verDetalleEtapas(gdoId: number): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/gestion-documentos/ver-detalle-etapas/${gdoId}`,
            { headers: this.getHeaders() }
        );
    }
}
