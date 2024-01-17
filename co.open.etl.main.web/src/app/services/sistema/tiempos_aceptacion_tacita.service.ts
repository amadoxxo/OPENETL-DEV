import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {BaseService} from '../core/base.service';

@Injectable()
export class TiemposAceptacionTacitaService extends BaseService{

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
     * Obtiene un tiempo de aceptación tácita.
     * 
     * @param tatId Identificador del tiempo de aceptación tácita
     */
    getTiempoAceptacionTacita (tatId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/tiempos-aceptacion-tacita/${tatId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo tiempo de aceptación tácita.
     * 
     * @param values Valores a registrar
     */
    createTiempoAceptacionTacita (values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/tiempos-aceptacion-tacita`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un tiempo de aceptación tácita.
     * 
     * @param values Valores a registrar
     * @param festivoId Idntificador del tiempo de aceptación tácita
     */
    updateTiempoAceptacionTacita (values, tatId): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}sistema/tiempos-aceptacion-tacita/${tatId}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de tiempos de aceptación tácita.
     * 
     * @param params
     */
    listarTiemposAceptacionTacita(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}sistema/lista-tiempos-aceptacion-tacita`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Elimina un tiempo de aceptación tácita.
     * 
     * @param tatId
     */
    deleteTiempoAceptacionTacita (tatId): Observable<any> {
        return this.authHttp.delete(
            `${this.apiUrl}sistema/tiempos-aceptacion-tacita/${tatId}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de tiempos de aceptación tácita en lote.
     * 
     * @param values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/tiempos-aceptacion-tacita/cambiar-estado`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }
}
