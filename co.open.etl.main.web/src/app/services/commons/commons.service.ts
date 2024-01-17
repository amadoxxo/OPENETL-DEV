import {Injectable} from '@angular/core';
import {BaseService} from '../core/base.service';
import {HttpClient, HttpParams} from '@angular/common/http';
import {Observable} from "rxjs";

@Injectable()
export class CommonsService extends BaseService {

    // Usuario en línea
    public usuario: any;

    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     * @memberof CommonsService
     */
    constructor(public authHttp: HttpClient) {
        super();
    }

    /**
     * Retorna una colección de datos necesarios comunes para contruir un adquirente,proveedor u oferente.
     * 
     * @param params string 
     * @memberof CommonsService
     */
    getDataInitForBuild(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        return this.authHttp.get(
            `${this.apiUrl}commons/get-data-init-for-build`,
            {headers: this.getHeaders(), params: queryParams}
        );
    }

    /**
     * Retorna una colección de datos necesarios comunes para contruir un adquirente,proveedor u oferente.
     * 
     * @param params string 
     * @memberof CommonsService
     */
    getParametrosDocumentosElectronicos(params: string = ''): Observable<any> {
        let queryParams = null;
        if(params)
            queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}commons/get-parametros-documentos-electronicos`,
            {headers: this.getHeaders(), params: queryParams}
        );
    }

    /**
     * Calcula el Dígito de Verificación para los NITs.
     * 
     * @param identificacion string NIT al cual calcularle el dígito de verificación
     * @memberof CommonsService
     */
    calcularDV(identificacion: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}commons/get-digito-verificacion?identificacion=${identificacion}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Retorna el listado de los Oferentes según los parametros enviados.
     *
     * @param {string} params Parámetros de la petición
     * @memberof CommonsService
     * @return {Observable<any>}
     */
    getOfes(params: string = ''): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        return this.authHttp.get(
            `${this.apiUrl}commons/get-ofes`,
            {headers: this.getHeaders(), params: queryParams}
        );
    }
}