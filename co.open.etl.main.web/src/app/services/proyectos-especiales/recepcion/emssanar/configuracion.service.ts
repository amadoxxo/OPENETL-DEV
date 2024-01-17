import {Injectable} from '@angular/core';
import {HttpClient, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {BaseService} from '../../../core/base.service';


@Injectable()
export class ConfiguracionService extends BaseService {

    public slug: string;

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }


    /**
     * Método Set Slug para obtener la ruta de la configuracion que se este llamando.
     *
     * @memberof ConfiguracionService
     */
    set setSlug(slug: string) {
        this.slug = slug
    }

    /**
     * Obtiene un registro.
     *
     * @param {string | number} id
     * @return {Observable<any>}
     * @memberof ConfiguracionService
     */
    get(id: string | number): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/configuracion/${this.slug}/${id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo registro.
     *
     * @param {any} payload
     * @return {Observable<any>}
     * @memberof ConfiguracionService
     */
    create(payload: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/configuracion/${this.slug}`,
            payload,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Actualiza un registro.
     *
     * @param {any} payload
     * @param {string | number} id
     * @return {Observable<any>}
     * @memberof ConfiguracionService
     */
    update(payload: any, id: string | number): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/configuracion/${this.slug}/${id}`,
            payload,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Obtiene una lista de registros.
     *
     * @param {string} params
     * @return {Observable<any>}
     * @memberof ConfiguracionService
     */
    listar(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/configuracion/${this.slug}/listar`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de registros en lote.
     *
     * @param {any} values
     * @return {Observable<any>}
     * @memberof ConfiguracionService
     */
    cambiarEstado(values: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/configuracion/${this.slug}/cambiar-estado`,
            values,
            { headers: this.getHeadersApplicationJSON() }
        )
    }
}
