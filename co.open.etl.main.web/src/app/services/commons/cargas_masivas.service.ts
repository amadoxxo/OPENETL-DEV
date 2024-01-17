import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from "rxjs/operators"
import {BaseService} from '../core/base.service';

@Injectable()
export class CargasMasivasService extends BaseService{

    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Descarga la interfaz de subida en masa.
     * 
     */
    generarInterface(tipo) {
        let slug;
        let param;
        switch (tipo) {
            case 'USU':
                slug = 'sistema/usuarios/generar-interface-usuarios';
                break;
            case 'ADQ':
                slug = 'configuracion/adquirentes/generar-interface-adquirentes';
                param = {tipoAdquirente: 'adquirente'};
                break;
            case 'AUT':
                slug = 'configuracion/autorizados/generar-interface-autorizados';
                param = {tipoAdquirente: 'autorizado'};
                break;
            case 'RES':
                slug = 'configuracion/responsables/generar-interface-responsables';
                param = {tipoAdquirente: 'responsable'};
                break;
            case 'VEN':
                slug = 'configuracion/vendedores-ds/generar-interface-vendedores-ds';
                param = {tipoAdquirente: 'vendedor'};
                break;
            case 'PROV':
                slug = 'configuracion/proveedores/generar-interface-proveedores';
                break;
            case 'OFE':
                slug = 'configuracion/ofe/generar-interface-ofe';
                break;
            case 'ACTOR':
                slug = 'configuracion/radian/actor/generar-interface-actor';
                break;
            case 'RFA':
                slug = 'configuracion/resoluciones-facturacion/generar-interface-resoluciones-facturacion';
                break;
            case 'SPT':
                slug = 'configuracion/spt/generar-interface-spt';
                break;
            case 'AED':
                slug = 'configuracion/autorizaciones-eventos-dian/generar-interface-autorizaciones-eventos-dian';
                break;
            case 'USUECM':
                slug = 'configuracion/usuarios-ecm/generar-interface-usuarios-ecm';
                break;
            case 'DMCARGOS':
                slug = 'facturacion-web/parametros/cargos/generar-interface';
                break;
            case 'DMDESCUENTOS':
                slug = 'facturacion-web/parametros/descuentos/generar-interface';
                break;
            case 'DMPRODUCTOS':
                slug = 'facturacion-web/parametros/productos/generar-interface';
                break;
            case 'EMP':
                slug = 'configuracion/nomina-electronica/empleadores/generar-interface-empleadores';
                break;
            case 'TRA':
                slug = 'configuracion/nomina-electronica/trabajadores/generar-interface-trabajadores';
                break;
            case 'GRUPOTRABAJO':
                slug = 'configuracion/grupos-trabajo/generar-interface-grupos-trabajo';
                break;
            case 'ASOCIARUSUARIO':
                slug = 'configuracion/grupos-trabajo-usuarios/generar-interface-grupos-trabajo-usuarios';
                break;
            case 'ASOCIARPROVEEDOR':
                slug = 'configuracion/grupos-trabajo-proveedores/generar-interface-grupos-trabajo-proveedores';
                break;
            default:
                break;
        }
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}${slug}`,
            param,
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
     * Descarga la interfaz de subida en masa para usuarios de portal de proveedores.
     *
     * @param string tipo Tipo de portal destino
     * @returns
     * @memberof CargasMasivasService
     */
    generarInterfaceUsuariosPortales(tipo: string) {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        let endpoint;
        if(tipo === 'proveedores')
            endpoint = 'configuracion/proveedores/generar-interface-usuarios-portal-proveedores';
        else if(tipo === 'clientes')
            endpoint = 'configuracion/adquirentes/generar-interface-usuarios-portal-clientes';

        return this.authHttp.post<Blob>(
            `${this.apiUrl}${endpoint}`,
            '',
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
     * Efectúa la carga masiva mediante excel.
     * 
     * @param fileToUpload any Archivo con elementos a cargar
     * @param tipo Tipo de paramétrica a subir
     */
    cargar(fileToUpload: any, tipo): Observable<any> {
        let slug;
        let parametro;
        switch (tipo) {
            case 'USU':
                slug = 'sistema/usuarios/cargar-usuarios';
                break;
            case 'ADQ':
                slug = 'configuracion/adquirentes/cargar-adquirentes';
                parametro = 'adquirente';
                break;
            case 'AUT':
                slug = 'configuracion/autorizados/cargar-autorizados';
                parametro = 'autorizado';
                break;
            case 'RES':
                slug = 'configuracion/responsables/cargar-responsables';
                parametro = 'responsable';
                break;
            case 'VEN':
                slug = 'configuracion/vendedores-ds/cargar-vendedores-ds';
                parametro = 'vendedor';
                break;
            case 'PROV':
                slug = 'configuracion/proveedores/cargar-proveedores';
                break;
            case 'OFE':
                slug = 'configuracion/ofe/cargar-ofe';
                break;
            case 'ACTOR':
                slug = 'configuracion/radian/actor/cargar-actor'; 
                break;
            case 'RFA':
                slug = 'configuracion/resoluciones-facturacion/cargar-resoluciones-facturacion';
                break;
            case 'SPT':
                slug = 'configuracion/spt/cargar-spt';
                break;
            case 'AED':
                slug = 'configuracion/autorizaciones-eventos-dian/cargar-autorizaciones-eventos-dian';
                break;
            case 'USUECM':
                slug = 'configuracion/usuarios-ecm/cargar-usuarios-ecm';
                break;
            case 'DMCARGOS':
                slug = 'facturacion-web/parametros/cargos/cargar';
                break;
            case 'DMDESCUENTOS':
                slug = 'facturacion-web/parametros/descuentos/cargar';
                break;
            case 'DMPRODUCTOS':
                slug = 'facturacion-web/parametros/productos/cargar';
                break;
            case 'EMP':
                slug = 'configuracion/nomina-electronica/empleadores/cargar-empleadores';
                break;
            case 'TRA':
                slug = 'configuracion/nomina-electronica/trabajadores/cargar-trabajadores';
                break;
            case 'GRUPOTRABAJO':
                slug = 'configuracion/grupos-trabajo/cargar-grupos-trabajo';
                break;
            case 'ASOCIARUSUARIO':
                slug = 'configuracion/grupos-trabajo-usuarios/cargar-grupos-trabajo-usuarios';
                break;
            case 'ASOCIARPROVEEDOR':
                slug = 'configuracion/grupos-trabajo-proveedores/cargar-grupos-trabajo-proveedores';
                break;
            default:
                break;
        }
        
        const INPUT = new FormData();
        INPUT.append('archivo', fileToUpload);
        INPUT.append('tipoAdquirente', parametro);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}${slug}`,
            INPUT,
            { headers: headers }
        );
    }

    /**
     * Efectúa la carga masiva mediante excel de Usuarios Portal Proveedores o Clientes.
     *
     * @param binary fileToUpload Archivo de Excel
     * @param string tipo Tipo de portal destino
     * @returns Observable<any>
     * @memberof CargasMasivasService
     */
    cargarUsuariosPortales(fileToUpload: any, tipo: string): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('archivo', fileToUpload);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        let endpoint;
        if(tipo === 'proveedores')
            endpoint = 'configuracion/proveedores/cargar-usuarios-portal-proveedores';
        else if(tipo === 'clientes')
            endpoint = 'configuracion/adquirentes/cargar-usuarios-portal-clientes';

        return this.authHttp.post(
            `${this.apiUrl}${endpoint}`,
            INPUT,
            { headers: headers }
        );
    }

    /**
     * Descarga la interfaz de subida en masa para la asignación de roles a los usuarios.
     *
     * @return {*} 
     * @memberof CargasMasivasService
     */
    generarInterfaceAsignacionRoles(): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiUrl}sistema/usuarios/generar-interface-asignacion-roles`,
            '',
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
     * Efectúa la carga masiva mediante excel para la asignación de roles a usuarios.
     *
     * @param binary fileToUpload Archivo de Excel
     * @returns Observable<any>
     * @memberof CargasMasivasService
     */
    cargarAsignacionRoles(fileToUpload: any): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('archivo', fileToUpload);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}sistema/usuarios/cargar-asignacion-roles`,
            INPUT,
            { headers: headers }
        );
    }
}
