import {Injectable} from '@angular/core';
import {HttpClient, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';
import {BaseService} from '../core/base.service';
import {Pais, PaisesInteface} from '../../main/models/pais.model';

@Injectable()
export class PaisesService extends BaseService{

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
     * Obtiene un pais.
     * 
     * @param paisId Identificador del pais
     */
    getPais(paisId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/paises/${paisId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo pais.
     * 
     * @param values Valores a registrar
     */
    createPais(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/paises`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un pais.
     * 
     * @param values Valores a registrar
     * @param paisId Idntificador del pais
     */
    updatePais(values, paisId): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}parametros/paises/${paisId}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de paises.
     * 
     * @param params
     */
    listarPaises(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}parametros/lista-paises`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Elimina un país.
     * 
     * @param paisId
     */
    deletePais(paisId): Observable<any> {
        return this.authHttp.delete(
            `${this.apiUrl}parametros/paises/${paisId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de paises en lote.
     * 
     * @param values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/paises/cambiar-estado`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Realiza una búsqueda predictiva de países dada su descripción o código desde un NgSelect
     */
    searchPaisesNgSelect(valor): Observable<Pais[]> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-paises/${valor}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: PaisesInteface) => rsp.data));
    }

    /**
     * Obtiene el registro de una paramétrica a partir de su código y fechas de vigencia.
     * 
     * @param values
     */
    consultaRegistroParametrica(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/paises/consulta-registro-parametrica`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }
}
