import {Injectable} from '@angular/core';
import {HttpClient, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';
import {BaseService} from '../core/base.service';
import {Municipio, MunicipioInteface} from '../../main/models/municipio.model';

@Injectable()
export class MunicipiosService extends BaseService{

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
     * Obtiene un municipio.
     * 
     * @param municipioId Identificador del municipio
     */
    getMunicipio (municipioId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/municipios/${municipioId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo municipio.
     * 
     * @param values Valores a registrar
     */
    createMunicipio (values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/municipios`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un municipio.
     * 
     * @param values Valores a registrar
     * @param municipioId Idntificador del municipio
     */
    updateMunicipio (values, municipioId): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}parametros/municipios/${municipioId}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de municipios.
     * 
     * @param params
     */
    listarMunicipios(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}parametros/lista-municipios`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Elimina un municipio
     * 
     * @param municipioId
     */
    deleteMunicipio (municipioId): Observable<any> {
        return this.authHttp.delete(
            `${this.apiUrl}parametros/municipios/${municipioId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de municipios en lote.
     * 
     * @param values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/municipios/cambiar-estado`,
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
     * Realiza una búsqueda predictiva de departamentos dada su descripción o código.
     * 
     * @param des Descripción del Departamento a buscar
     */
    searchDepartamentos(valor, paisId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-departamentos/${valor}/pais/${paisId}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda de un departamento dada su descripción
     * 
     * @param des Descripción del Departamento a buscar
     */
    buscarDepartamentoExacto(dep, pais): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/departamentos/dep_descripcion/valor/${dep}/pais/${pais}/filtro/exacto`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda de un país dada su descripción
     * 
     * @param des Descripción del País a buscar
     */
    buscarPaisExacto(paisDesc, paisId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/paises/pai_descripcion/valor/${paisDesc}/filtro/exacto`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda predictiva de municipios dada su descripción o código desde un NgSelect
     */
    searchMunicipioNgSelect(valor, pai_id, dep_id): Observable<Municipio[]> {
        let departamento;
        let pais
        if(dep_id && dep_id.dep_id)
            departamento = dep_id.dep_id
        else
            departamento = null;    
        if(pai_id && pai_id.pai_id)
            pais = pai_id.pai_id
        else
            pais = '';   
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-municipio/${valor}/pais/${pais}/departamento/${departamento}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: MunicipioInteface) => rsp.data));
    }
    
    /**
     * Obtiene el registro de una paramétrica a partir de su código y fechas de vigencia.
     * 
     * @param values
     */
    consultaRegistroParametrica(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/municipios/consulta-registro-parametrica`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }
}