import {Injectable} from '@angular/core';
import {BaseService} from '../core/base.service';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';
import {Oferente, OferenteInteface} from '../../main/models/oferente.model';

@Injectable()
export class OferentesService extends BaseService {
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
     * Realiza una búsqueda predictiva de ofes dada su descripción o nit desde un NgSelect.
     *
     * @param {*} valor Valor a buscar
     * @return {*} {Observable<Oferente[]>}
     * @memberof OferentesService
     */
    searchOferentesNgSelect(valor): Observable<Oferente[]> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/ofe/buscar-ng-select/${valor}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: OferenteInteface) => rsp.data));
    }

    /**
     * Actualiza un OFE.
     *
     * @param {*} ofe_identificacion Identificación del OFE
     * @param {*} values Valores a registrar
     * @return {*} {Observable<any>}
     * @memberof OferentesService
     */
    update(ofe_identificacion, values): Observable<any> {
        const INPUT = new FormData();

        for (const prop in values) {
            if((prop === 'ofe_direcciones_adicionales' || prop === 'contactos')  && Array.isArray(values[prop]) && values[prop].length > 0){
                INPUT.append(prop, JSON.stringify(values[prop]));
            } else {
                INPUT.append(prop, values[prop]);
            }
        }

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.put(
            `${this.apiUrl}configuracion/ofe/${ofe_identificacion}`,
            INPUT,
            {headers: headers}
        );
    }

    /**
     * Actualiza la información relacionada con la representación gráfica estándar de un OFE para
     * El documento electrónico.
     * 
     * @param {*} logo Logo
     * @param {*} ofe_identificacion ID del OFE
     * @param {*} values Valores a registrar
     * @param {string} tipoConfiguracion
     * @return {*} {Observable<any>}
     * @memberof OferentesService
     */
    updateConfiguracionDocumento(logo, ofe_identificacion, values, tipoConfiguracion: string): Observable<any> {
        const INPUT = new FormData();
        if(logo) INPUT.append('logo', logo);
            INPUT.append('ofe_identificacion', ofe_identificacion);

        for (const prop in values) {
            if(values[prop]) {
                INPUT.append(prop, values[prop]);
            }
        }
        let ruta = (tipoConfiguracion === 'DS') ? 'documento-soporte' : 'documento-electronico';

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.post(
            `${this.apiUrl}configuracion/ofe/configuracion-${ruta}`,
            INPUT,
            {headers: headers}
        );
    }

    /**
     * Actualiza la información relacionada con los datos de documentos manuales.
     * 
     * @param {string} values Valores a registrar
     * @return {*} {Observable<any>}
     * @memberof OferentesService
     */
    updateDatosFacturacionWeb(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}configuracion/ofe/datos-facturacion-web`,
            this._parseObject(values),
            {headers: this.getHeaders()}
        );
    }

    /**
     * Guarda un OFE.
     * 
     * @param values Valores a registrar
     * @return {*} {Observable<any>}
     * @memberof OferentesService
     */
    create(values): Observable<any> {
        const INPUT = new FormData();

        for (const prop in values) {
            if((prop === 'ofe_direcciones_adicionales' || prop === 'contactos')  && Array.isArray(values[prop]) && values[prop].length > 0){
                INPUT.append(prop, JSON.stringify(values[prop]));
            } else {
                INPUT.append(prop, values[prop]);
            }
        }

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}configuracion/ofe`,
            INPUT,
            {headers: headers}
        );
    }

    /**
     * Crea el Adquirente Consumidor Final para uno o varios OFEs.
     * 
     * @param values Parametros de la petición
     * @return {*} {Observable<any>}
     * @memberof OferentesService
     */
    crearAdquirenteConsumidorFinal(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}configuracion/ofe/crear-adquirente-consumidor-final`,
            values,
            { headers: this.getHeadersApplicationJSON() }
        )
    }

    /**
     * Obtiene las resoluciones de facturación de un OFE.
     * 
     * @param values Parametros de la petición
     * @return {*} {Observable<any>}
     * @memberof OferentesService
     */
    getResolucionesFacturacionOfe(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}configuracion/ofe/lista-resoluciones-facturacion`,
            values,
            { headers: this.getHeadersApplicationJSON() }
        )
    }

    /**
     * Actualiza la configuración de servicios para un Ofe.
     *
     * @param {*} logoNotificacionEventosDian Logo de para notificación de eventos DIAN
     * @param {*} logoCadisoft Logo de integración con Cadisoft
     * @param {*} values Valores a registrar
     * @return {*}  {Observable<any>}
     * @memberof OferentesService
     */
    actualizarConfiguracionServicios(logoNotificacionEventosDian, logoCadisoft, values): Observable<any> {
        const INPUT = new FormData();
        
        if(logoNotificacionEventosDian)
            INPUT.append('logoNotificacionEventosDian', logoNotificacionEventosDian);
            
        if(logoCadisoft)
            INPUT.append('logoCadisoft', logoCadisoft);

        for (const prop in values) {
            if((prop == 'ofe_emision_eventos_contratados_titulo_valor' || prop == 'ofe_recepcion_eventos_contratados_titulo_valor') && Array.isArray(values[prop]) && values[prop].length > 0){
                INPUT.append(prop, JSON.stringify(values[prop]));
            } else if(prop === 'ofe_cadisoft_configuracion' || prop === 'ofe_eventos_notificacion' || prop === 'ofe_conexion_smtp') {
                INPUT.append(prop, JSON.stringify(values[prop]));
            } else {
                INPUT.append(prop, values[prop]);
            }
        }

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.post(
            `${this.apiUrl}configuracion/ofe/configuracion-servicios`,
            INPUT,
            {headers: headers}
        );
    }
}
