import {Injectable} from '@angular/core';
import {HttpClient, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';
import {BaseService} from '../core/base.service';
import {Departamento, DepartamentoInteface} from '../../main/models/departamento.model';

@Injectable()
export class DepartamentosService extends BaseService{

    // Usuario en línea
    public usuario: any;

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene un departamento.
     * 
     * @param departamentoId Identificador del departamento
     */
    getDepartamento (departamentoId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/departamentos/${departamentoId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo departamento.
     * 
     * @param values Valores a registrar
     */
    createDepartamento (values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/departamentos`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un departamento.
     * 
     * @param values Valores a registrar
     * @param departamentoId Idntificador del departamento
     */
    updateDepartamento (values, departamentoId): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}parametros/departamentos/${departamentoId}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de departamentos.
     * 
     * @param params
     */
    listarDepartamentos(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}parametros/lista-departamentos`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Elimina un país.
     * 
     * @param departamentoId
     */
    deleteDepartamento (departamentoId): Observable<any> {
        return this.authHttp.delete(
            `${this.apiUrl}parametros/departamentos/${departamentoId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de departamentos en lote.
     * 
     * @param values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/departamentos/cambiar-estado`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Realiza una búsqueda predictiva de países dada su descripción o código.
     * 
     * @param des Descripción del País a buscar
     */
    searchPaises(valor): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-paises/${valor}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda predictiva de departamentos dada su descripción o código desde un NgSelect
     */
    searchDepartamentoNgSelect(valor, pai_id): Observable<Departamento[]> {
        let pais;
        if(pai_id && pai_id.pai_id)
            pais = pai_id.pai_id
        else
            pais = null;    
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-departamentos/${valor}/pais/${pais}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: DepartamentoInteface) => rsp.data));
    }
}
