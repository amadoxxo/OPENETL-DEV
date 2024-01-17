import {Injectable} from '@angular/core';
import {BaseService} from '../core/base.service';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';
import {Proveedor, ProveedorInteface} from '../../main/models/proveedor.model';

@Injectable()
export class ProveedoresService extends BaseService {
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
     * Realiza una búsqueda predictiva de proveedores dada su descripción o nit desde un NgSelect
     */
    searchProveedorNgSelect(valor, ofe_id = null): Observable<Proveedor[]> {
        let url = `configuracion/proveedores/search-proveedores?buscar=${valor}&ofe_id=${ofe_id}`;
        return this.authHttp.get(
            `${this.apiUrl}${url}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: ProveedorInteface) => rsp.data));
    }

    /**
     * Actualiza los usuarios de portales de un proveedor
     *
     * @param object values Valor a transmisitr
     * @returns {Observable<any>}
     * @memberof ProveedoresService
     */
    actualizarUsuariosPortales(values): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Content-type', 'application/json')
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}configuracion/proveedores/actualizar-usuarios-portales`,
            values,
            {headers: headers}
        );
    }

    /**
     * Actualiza el estado de un usuario de portales de un proveedor
     *
     * @param object values Valor a transmisitr
     * @returns {Observable<any>}
     * @memberof ProveedoresService
     */
    actualizarEstadoUsuarioPortales(values): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Content-type', 'application/json')
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}configuracion/proveedores/actualizar-estado-usuario-portales`,
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
            `${this.apiUrl}configuracion/proveedores/descargar-lista-usuarios-portales`,
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