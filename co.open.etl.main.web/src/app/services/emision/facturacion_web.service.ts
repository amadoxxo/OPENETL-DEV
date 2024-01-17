import {Injectable, Inject, Optional} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {Documento, DocumentoInteface} from '../../main/models/documento.model';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class FacturacionWebService extends BaseService{
    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param {string} params Parámetros a enviar
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    descargarExcel(params: string, origen: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        let headers = new HttpHeaders();
        headers     = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers     = headers.set('Pragma', 'no-cache');
        headers     = headers.set('Expires', '0');

        return this.authHttp.get<Blob>(
            `${this.apiUrl}emision/facturacion-web/parametros/lista-${origen}`,
            {
                headers: headers,
                params: queryParams,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }

    /**
     * Obtiene una lista paginada de registros.
     * 
     * @param {string} params Parámetros a enviar
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    listarRegistros(params: string, origen: string): Observable<any> {
        const queryParams = new HttpParams({ fromString: params });

        return this.authHttp.get(
            `${this.apiUrl}emision/facturacion-web/parametros/lista-${origen}`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de registros en lote.
     * 
     * @param {array} values IDs de los registros a cambiar de estado
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    cambiarEstadoRegistros(values: Array<any>, origen: string): Observable<any> {
        let object: any = {'codigos': values.join(',')};

        if(origen === 'cargos')
            object = {'dmc_ids': values.join(',')};
        if(origen === 'descuentos')
            object = {'dmd_ids': values.join(',')};
        if(origen === 'productos')
            object = {'dmp_ids': values.join(',')};

        return this.authHttp.post(
            `${this.apiUrl}emision/facturacion-web/parametros/${origen}/cambiar-estado`,
            this._parseObject(object),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Crea un nuevo registro.
     * 
     * @param {any} values Valores a registrar
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    createRegistro(values: any, origen: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/facturacion-web/parametros/${origen}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un registro.
     * 
     * @param {any} values Valores a registrar
     * @param {number} id Identificador del registro
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    updateRegistro(values: any, id: number, origen: string): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}emision/facturacion-web/parametros/${origen}/${id}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la información de un registro.
     * 
     * @param {any} values Valores a registrar
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    getProducto(values: any, origen: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/facturacion-web/parametros/${origen}/consultar-producto`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }
}
