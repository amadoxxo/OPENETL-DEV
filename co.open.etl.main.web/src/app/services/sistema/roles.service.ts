import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import {BaseService} from '../core/base.service';

@Injectable()
export class RolesService extends BaseService{

    // Usuario en línea
    public usuario: any;

    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene un rol.
     * 
     * @param rol_id Identificador del rol
     */
    getRol (rol_id): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/roles/${rol_id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo rol.
     * 
     * @param values Valores a registrar
     */
    createRol (values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/roles`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un rol.
     * 
     * @param values Valores a registrar
     * @param rol_id Identificador del rol
     */
    updateRol (values, rol_id): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}sistema/roles/${rol_id}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Retorna una lista de roles.
     * 
     * @param parameters
     */
    listarRoles(parameters): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/lista-roles?${parameters}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Retorna una lista de permisos.
     * 
     * @param parameters
     */
    listarPermisos(parameters): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/lista-recursos?${parameters}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Establece los roles requeridos por un recurso.
     * 
     * @param rol_id Identificador del rol
     * @param rec_id Identificador del recurso
     */
    setPermisoRol (rol_id, rec_id): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/permisos/${rol_id}/${rec_id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Remueve un rol que ha sido asignado a un recurso.
     * 
     * @param rol_id Identificador del rol
     * @param rec_id Identificador del recurso
     */
    unsetPermisoRol (rol_id, rec_id): Observable<any> {
        return this.authHttp.delete(
            `${this.apiUrl}sistema/permisos/${rol_id}/${rec_id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Efectúa las acciones en lotes para activar o desactivar recursos.
     * 
     * @param accion Activar o descativar
     * @param rol_id Identificador del rol
     * @param arrRecursosId lista de recursos a activar o inhabilitar separada por comas
     */
    actDesBloquePermisos (accion, rol_id, arrRecursosId): Observable<any> {
        let recursosId = arrRecursosId.join();

        if (accion === 'activar') {
            return this.authHttp.post(
                `${this.apiUrl}sistema/permisos-multiple/${rol_id}/${recursosId}`,
                { headers: this.getHeaders() }
            );
        } else {
            return this.authHttp.delete(
                `${this.apiUrl}sistema/permisos-multiple/${rol_id}/${recursosId}`,
                { headers: this.getHeaders() }
            );
        }
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de roles en lote.
     * 
     * @param values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/roles/cambiar-estado`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }
}
