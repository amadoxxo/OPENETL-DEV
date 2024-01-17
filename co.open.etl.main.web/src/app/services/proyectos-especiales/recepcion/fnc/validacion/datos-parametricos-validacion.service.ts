import {Injectable} from '@angular/core';
import {HttpClient, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {BaseService} from '../../../../core/base.service';

@Injectable()
export class DatosParametricosValidacionService extends BaseService {
    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene una lista de Datos Paramétricos de Validación conforme a la clasificación.
     * 
     * @param {string} campo Campo del formulario
     * @param {string} clasificacion Clasificación para la cual se debe obtener la paramétrica
     * @return {Observable}
     * @memberof DatosParametricosValidacionService
     */
    listarDatosParametricosValidacion(campo: string, clasificacion: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/fnc/validacion/listar-datos-parametricos-validacion/${campo}/${clasificacion}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la información de un dato paramétrico de validación.
     * 
     * @param {number} dpv_id ID del dato paramétrico de validación a consultar
     * @return {Observable}
     * @memberof DatosParametricosValidacionService
     */
    verDatoParametricoValidacion(dpv_id: number): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/fnc/validacion/ver-dato-parametrico-validacion/${dpv_id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de Datos Paramétricos de Validación conforme a la clasificación.
     * 
     * @param {string} queryParams Parámetros de la petición
     * @return {Observable}
     * @memberof DatosParametricosValidacionService
     */
    listaPaginadaDatosParametricosValidacion(queryParams: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/recepcion/fnc/validacion/lista-paginada-datos-parametricos-validacion?${queryParams}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un dato paramétrico de validación.
     *
     * @param {Object} values Objeto que contiene los valores requeridos para crear el dato paramétrico de validación
     * @return {Observable<any>}
     * @memberof DatosParametricosValidacionService
     */
    crearDatosParametricosValidacion(values: Object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/fnc/validacion/crear-datos-parametricos-validacion`,
            values,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Cambia el estado para los datos paramétricos de validación seleccionados.
     *
     * @param {Object} values Objeto que contiene los valores requeridos para crear el dato paramétrico de validación
     * @return {Observable<any>}
     * @memberof DatosParametricosValidacionService
     */
    cambiarEstadoDatosParametricosValidacion(values: Object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/fnc/validacion/cambiar-estado-datos-parametricos-validacion`,
            values,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Edita o modifica un dato paramétrico de validación.
     *
     * @param {Object} values Objeto que contiene los valores requeridos para editar el dato paramétrico de validación
     * @param {number} dpv_id ID del dato paramétrico de validación que se está modificando
     * @return {Observable<any>}
     * @memberof DatosParametricosValidacionService
     */
    editarDatoParametricsValidacion(values: Object, dpv_id: number): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}proyectos-especiales/recepcion/fnc/validacion/editar-dato-parametrico-validacion/${dpv_id}`,
            values,
            { headers: this.getHeadersApplicationJSON() }
        );
    }
}
