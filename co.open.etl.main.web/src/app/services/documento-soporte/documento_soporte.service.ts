import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentoSoporteService extends BaseService{

    // Usuario en línea
    public usuario: any;
    public slug: string;

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Genera la interfaz de excel para los Documento Soporte.
     *
     * @param {number} ofe_id Id del Oferente
     * @return {*} 
     * @memberof DocumentoSoporteService
     */
    generarInterfaceDocumentosSoporte(ofe_id: number) {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}documento-soporte/documentos/generar-interface-documento-soporte/${ofe_id}`,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }

    /**
     * Genera la interfaz de excel para la Nota Crédito Documento Soporte.
     *
     * @param {number} ofe_id Id del Oferente
     * @return {*} 
     * @memberof DocumentoSoporteService
     */
    generarInterfaceNotaCreditoDocumentosSoporte(ofe_id: number) {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}documento-soporte/documentos/generar-interface-nota-credito-documento-soporte/${ofe_id}`,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }

    /**
     * Permite la carga de Documentos Soporte.
     *
     * @param {*} ofe_id Id del Oferente
     * @param {*} fileToUpload Documento manual a cargar
     * @param {string} evento string Tipo de archivo a cargar
     * @return {*}  {Observable<any>}
     * @memberof DocumentoSoporteService
     */
    cargarDocumentosSoportePorExcel(ofe_id: any, evento: string, fileToUpload: any): Observable<any> {
        let slug;
        const INPUT = new FormData();
        INPUT.append('archivo', fileToUpload);
        INPUT.append('ofe_id', ofe_id);

        if (evento === 'subir_ds') {
            slug = 'documentos/cargar-documento-soporte';
        } else if (evento === 'subir_ds_nc') {
            slug = 'documentos/cargar-nota-credito-documento-soporte';
        }

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiDIUrl}${slug}`,
            INPUT,
            { headers: headers }
        );
    }
}
