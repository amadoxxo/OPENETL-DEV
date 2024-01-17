import { Observable } from "rxjs";
import { Injectable } from '@angular/core';
import { BaseService } from '../core/base.service';
import { HttpClient } from '@angular/common/http';

@Injectable()
export class OpenEcmService extends BaseService {

    // Usuario en línea
    public usuario: any;
    
    public aplicaModuloEcm: boolean = false;
    public ofeEcm: any;

    /**
     * Constructor
     * @param _http Cliente http
     */
    constructor(public _http: HttpClient) {
        super();
    }

    /**
     * Login a openECM desde openETL.
     * 
     * @param {string} url URL de openECM para realizar el login
     * @param {*} email Correo del usuario autenticado actualmente en el sistema
     * @return {*}  {Observable<any>}
     * @memberof OpenEcmService
     */
    loginECM(url:string, email): Observable<any>{
        return this._http.post(
            `${url}`,
            this._parseObject(email),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Valida las condiciones para mostrar el Visor a ECM.
     * 
     * @param {*} ofe Información del oferente seleccionado
     * @return {boolean} Valor booleando para cuando aplica o no el Visor a openECM
     * @memberof OpenEcmService
     */
    validarVisorEcm(ofe): boolean {
        this.ofeEcm = ofe;
        if(ofe.ofe_integracion_ecm_conexion && ofe.ofe_integracion_ecm_conexion !== null) {
            let dataOfeEcm = JSON.parse(ofe.ofe_integracion_ecm_conexion);
            dataOfeEcm.servicios.forEach(data => {
                if(data.modulo === 'Emision')
                    this.aplicaModuloEcm = true;
            });
        }

        if(ofe.ofe_integracion_ecm === 'SI' && this.aplicaModuloEcm === true && ofe.integracion_variable_ecm === 'SI' && ofe.integracion_usuario_ecm === 'SI')
            return true;
        else
            return false;
    }
    /**
     * Retorna la información del OFE actualmente seleccionado.
     *
     * @return {*} Información del último ofe enviado como parametro en el metodo validarVisorEcm()
     * @memberof OpenEcmService
     */
    dataOfeVisorEcm(){
        return this.ofeEcm;
    }
}