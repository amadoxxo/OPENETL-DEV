import {Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from "rxjs/operators"
import {BaseService} from '../core/base.service';

export interface Item {
   
}

export interface ItemInteface {
    items: Item[];
}

export interface Tributo extends Item {
   tri_id: number;
   tri_nombre: string;
}

export interface TributoInterface extends Item{
    tributos: Tributo[];
}

@Injectable()
export class BusquedasPredictivasService extends BaseService{

    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene una lista de ítems predictivos.
     *
     * @param {string} parametricaSlug
     * @param {string} entrada
     * @return {*}  {Observable<Item[]>}
     * @memberof BusquedasPredictivasService
     */
    getItemsPredictivos(parametricaSlug: string, entrada: string): Observable<Item[]> {
        return this.authHttp.get(
            `${this.apiUrl}${parametricaSlug}-busqueda-predictiva&search=${entrada}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp:ItemInteface) => rsp.items));
    }
}
