import { Observable } from 'rxjs';
import { Injectable } from '@angular/core';
import { HttpClient} from '@angular/common/http';
// import { User } from '../../main/models/user';
import {BaseService} from '../core/base.service';

@Injectable()
export class PerfilUsuarioService extends BaseService{

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor(public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene un usuario
     * @param id Identificador del usuario
     */
    getUser(id: number): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/usuarios/${id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un usuario
     * @param user Usuario a actualizar
     */
    updateUser(user: any): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}sistema/usuarios/${user.usu_id}`,
            this._parseObject(user),
            { headers: this.getHeaders() }
        );
    }

    /**
     * cambia la contrañse
     * @param values Datos a procesar
     */
    changePass(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}password/change`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Registra un nuevo usuario
     * @param user Datos a registrar
     */
    createUser(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/usuarios`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene los datos del usuario autenticado
     */
    getDatosUsuarioAutenticado(): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/usuarios/datos-usuario-autenticado`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza los datos del usuario autenticado
     * @param user Datos del usuario
     */
    updateDatosUsuarioAutenticado(user: any): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}sistema/usuarios/actualizar-datos-usuario-autenticado`,
            this._parseObject(user),
            { headers: this.getHeaders() }
        );
    }
}
