import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {BaseService} from '../core/base.service';

@Injectable()
export class FestivosService extends BaseService{

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
     * Obtiene un festivo.
     * 
     * @param festivoId Identificador del festivo
     */
    getFestivo (festivoId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/festivos/${festivoId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo festivo.
     * 
     * @param values Valores a registrar
     */
    createFestivo (values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/festivos`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un festivo.
     * 
     * @param values Valores a registrar
     * @param festivoId Idntificador del festivo
     */
    updateFestivo (values, festivoId): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}sistema/festivos/${festivoId}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de festivos.
     * 
     * @param params
     */
    listarFestivos(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}sistema/lista-festivos`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Elimina un festivo.
     * 
     * @param festivoId
     */
    deleteFestivo (festivoId): Observable<any> {
        return this.authHttp.delete(
            `${this.apiUrl}sistema/festivos/${festivoId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de festivos en lote.
     * 
     * @param values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/festivos/cambiar-estado`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }
}
