import {Observable} from "rxjs";
import {map} from 'rxjs/operators';
import {Injectable} from '@angular/core';
import {BaseService} from '../core/base.service';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Adquirente, AdquirenteInteface} from '../../main/models/adquirente.model';

@Injectable()
export class AdquirentesService extends BaseService {

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
     * Retorna el listado de adquirentes
     */
    getAdquirentes(): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/adquirentes`,
            {headers: this.getHeaders()}
        );
    }

    getAdquirenteSelect(ofeId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/adquirentes/listar-select/` + ofeId,
            {headers: this.getHeaders()}
        );
    }

    getAdquirente(adqId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/adquirentes/${adqId}`,
            {headers: this.getHeaders()}
        );
    }

    crearAdquirente(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}configuracion/adquirentes`,
            this._parseObject(values),
            {headers: this.getHeaders()}
        );
    }

    actualizarAdquirente(values, adqId): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}configuracion/adquirentes/${adqId}`,
            this._parseObject(values),
            {headers: this.getHeaders()}
        );
    }

    cambiaEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}configuracion/adquirentes/cambiar-estado`,
            this._parseObject(values),
            {headers: this.getHeaders()}
        );
    }

    getAdquirenteListaSelect(): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/adquirentes/listar-select`,
            {headers: this.getHeaders()}
        );
    }

    getAdquirenteModal(campoBuscar: string, valorBuscar: string, filtroColumnas: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/adquirente/${campoBuscar}/valor/${valorBuscar}/filtro/${filtroColumnas}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda predictiva de adquirentes dada su descripción o nit desde un NgSelect.
     *
     * @param {*} valor Valor a buscar
     * @param {*} ofe_id Id del Oferente
     * @param {boolean} [autorizados=false] Indica si aplica autorizados
     * @param {boolean} [proceso_pickup_cash=false] Indica si aplica proceso pickup cash
     * @param {boolean} [vendedor_ds=false] Indica si aplica vendedor
     * @return {*}  {Observable<Adquirente[]>}
     * @memberof AdquirentesService
     */
    searchAdquirentesNgSelect(valor, ofe_id, autorizados = false, proceso_pickup_cash = false, vendedor_ds = false): Observable<Adquirente[]> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/adquirentes/search-adquirentes?buscar=${valor}&ofe_id=${ofe_id}&autorizados=${autorizados}&proceso_pickup_cash=${proceso_pickup_cash}&vendedor_ds=${vendedor_ds}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: AdquirenteInteface) => rsp.data));
    }

    /**
     * Actualiza los usuarios de portales de un adquirente
     *
     * @param object values Valor a transmisitr
     * @returns {Observable<any>}
     * @memberof AdquirentesService
     */
    actualizarUsuariosPortales(values): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Content-type', 'application/json')
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}configuracion/adquirentes/actualizar-usuarios-portales`,
            values,
            {headers: headers}
        );
    }

    /**
     * Actualiza el estado de un usuario de portales de un adquirente
     *
     * @param object values Valor a transmisitr
     * @returns {Observable<any>}
     * @memberof AdquirentesService
     */
     actualizarEstadoUsuarioPortales(values): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Content-type', 'application/json')
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}configuracion/adquirentes/actualizar-estado-usuario-portales`,
            values,
            {headers: headers}
        );
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @returns
     */
     descargarExcelUsuariosPortales(params): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}configuracion/adquirentes/descargar-lista-usuarios-portales`,
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
}

