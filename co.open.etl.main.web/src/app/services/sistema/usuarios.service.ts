import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators"

import {BaseService} from '../core/base.service';
import {Oferente, OferenteInteface} from "../../main/models/oferente.model";
import {Usuario, UsuarioInteface} from "../../main/models/usuario.model";

@Injectable()
export class UsuariosService extends BaseService{

    // Usuario en línea
    public usuario: any;

    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor(public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene un usuario.
     * 
     * @param usuario_id Identificador del usuario
     */
    getUsuario(usuario_id): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/usuarios/${usuario_id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo usuario.
     * 
     * @param values Valores a registrar
     */
    createUsuario(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/usuarios`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un usuario.
     * 
     * @param values Valores a registrar
     * @param usuario_id Identificador del usuario
     */
    updateUsuario(values, usuario_id): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}sistema/usuarios/${usuario_id}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Retorna una lista de usuarios.
     * 
     * @param parameters
     */
    listarUsuarios(parameters): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/lista-usuarios?${parameters}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de usuarios en lote.
     * 
     * @param values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/usuarios/cambiar-estado`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de registros en lote.
     * 
     * @param values
     */
    cambiarEstadoCodigos(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/usuarios/cambiar-estado`,
            values,
            { headers: this.getHeadersApplicationJSON() }
        )
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
     * Asigna un rol a un usuario.
     * 
     * @param usu_id Identificador del usuario
     * @param rol_id Identificador del rol
     */
    setRolUsuario(usu_id, rol_id): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/roles-usuarios/${rol_id}/${usu_id}`,
            { headers: this.getHeaders() }
        )
    }

    /**
     * Quita la asignación de un rol a un usuario.
     * 
     * @param usu_id Identificador del usuario
     * @param rol_id Identificador del rol
     */
    unsetRolUsuario(usu_id, rol_id): Observable<any> {
        return this.authHttp.delete(
            `${this.apiUrl}sistema/roles-usuarios/${rol_id}/${usu_id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Activa o desctiva roles a un usuario.
     * 
     * @param accion Accion a ejecutar
     * @param usu_id Identificador del usuario
     * @param arrRolesId Roles
     */
    actDesBloqueRoles(accion, usu_id, arrRolesId): Observable<any> {
        let rolesId = arrRolesId.join();

        if (accion === 'activar') {
            return this.authHttp.post(
                `${this.apiUrl}sistema/roles-usuarios-multiple/${usu_id}/${rolesId}`,
                { headers: this.getHeaders() }
            );
        } else {
            return this.authHttp.delete(
                `${this.apiUrl}sistema/roles-usuarios-multiple/${usu_id}/${rolesId}`,
                { headers: this.getHeaders() }
            );
        }
    }

    /**
     * Obtiene los ofes a los que el usuario le puede efectuar cargas masivas de archivos por de openIDE
     * @param usu_id
     */
    getOfesIdeUsuario(usu_id: number): Observable<any>  {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get(
            `${this.apiUrl}obtener-ofes-ide-usuario/${usu_id}`,
            {headers: headers}
        );
    }

     /**
      * Obtiene los ofes a los que el usuario le puede efectuar cargas masivas de archivos por de openIDE
      * @param usu_id
      */
    getProveedoresGestionables(usu_id: number): Observable<any>  {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get(
            `${this.apiUrl}proveedores-usuario/${usu_id}`,
            {headers: headers}
        );
    }

    /**
     * Realiza el cambio de clave rápido de un usuario.
     * 
     * @param values Valores a registrar
     */
    cambiaPassword (values, usuId): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/usuarios/cambiar-password/${usuId}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Descarga la consulta de usuarios en un archivo de Excel.
     * 
     * @param buscar
     */
    // descargarExcelListadoUsuarios(buscar: string): Observable<any> {
    //     const INPUT = new FormData();
    //     INPUT.append('buscar', buscar);

    //     let headers = new HttpHeaders();
    //     headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
    //     headers = headers.set('Pragma', 'no-cache');
    //     headers = headers.set('Expires', '0');

    //     return this.authHttp.post<Blob>(
    //         `${this.apiUrl}excel-usuarios`,
    //         INPUT,
    //         {
    //             headers: headers,
    //             responseType: 'blob' as 'json',
    //             observe: 'response'
    //         }
    //     ).pipe(
    //         map(response => this.downloadFile(response)),
    //         catchError(err => {
    //             let error = err.message ? err.message : 'Error en la descarga del Excel';
    //              return throwError(() => new Error(error));
    //         })
    //     );
    // }

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @returns
     */
    descargarExcelListadoUsuarios(params): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}sistema/lista-usuarios`,
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

    /**
     * Descarga la interfaz de subida en masa de usuarios
     */
    generarInterfaceSubida() {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}sistema/usuarios/generar-interface-usuarios`,
            {},
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
     * Efecuta la carga masiva de usuarios mediante excel
     * @param fileToUpload
     */
    cargarUsuarios(fileToUpload: any): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('archivo', fileToUpload);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}cargar-usuarios`,
            INPUT,
            { headers: headers }
        );
    }

    searchOfes(campoBuscar: string, valorBuscar: string, filtroColumnas: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/ofe/${campoBuscar}/valor/${valorBuscar}/filtro/${filtroColumnas}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Efectua una busqueda predictiva sobre la tabla de usuarios
     * @param valor
     */
    searchUsuariosNgSelect(valor): Observable<Usuario[]> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/obtener-usuarios/${valor}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: UsuarioInteface) => rsp.data));
    }

    /**
     * Obtiene los usuarios asignados a un oferente para cargas manuales
     * @param valor
     */
    obetenerUsuariosPorOfe(valor): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/obtener-lista-usuarios/${valor}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Permite la descarga del Excel con la lista de roles asignados a los usuarios.
     *
     * @return {*}  {Observable<any>}
     * @memberof UsuariosService
     */
    descargarExcelAsignacionRoles(): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.get<Blob>(
            `${this.apiUrl}sistema/usuarios/lista-roles-asignados`,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }
}
