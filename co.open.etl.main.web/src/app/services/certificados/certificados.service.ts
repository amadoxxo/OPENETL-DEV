import { Injectable } from '@angular/core';
import { BaseService } from '../core/base.service';
import { HttpClient } from '@angular/common/http';

@Injectable()
export class CertificadosService extends BaseService {

    /**
     * Crea una instancia de CertificadosService.
     * 
     * @param {HttpClient} authHttp
     * @memberof CertificadosService
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Consulta la fecha de vencimiento de los certificados del cliente al cual está asociado el usuario autenticado.
     *
     * @return {*} 
     * @memberof CertificadosService
     */
    consultaVencimientoCertificados() {
        return this.authHttp.get(
            `${this.apiUrl}vencimiento-certificado`,
            { headers: this.getHeaders() }
        );
    }
}








