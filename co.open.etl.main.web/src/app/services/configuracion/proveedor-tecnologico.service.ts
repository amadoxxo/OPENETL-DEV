import {Injectable} from '@angular/core';
import {BaseService} from '../core/base.service';
import {HttpClient} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';
import {ProveedorTecnologico, ProveedorTecnologicoInteface} from '../../main/models/proveedor-tecnologico.model';

@Injectable()
export class ProveedorTecnologicoService extends BaseService {
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
     * Realiza una búsqueda predictiva de ofes dada su descripción o nit desde un NgSelect
     */
    searchSftNgSelect(valor, aplica_para = 'DE'): Observable<ProveedorTecnologico[]> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/spt/buscar-ng-select/${valor}/aplicaPara/${aplica_para}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: ProveedorTecnologicoInteface) => rsp.data));
    }
}