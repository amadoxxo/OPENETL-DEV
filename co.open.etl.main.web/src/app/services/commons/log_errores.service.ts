import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators"
import {BaseService} from '../core/base.service';

@Injectable()
export class LogErroresService extends BaseService{

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene el listado de Errores para el tracking.
     * 
     */
    getListaLogErrores(params): Observable<any> {
        let ruta;
        switch (params.tipoLog) {
            case 'USU':
            case 'ASOCIARROLES':
                ruta = 'usuarios';
                break;
            case 'ADQ':
                ruta = 'adquirentes';
                params['tipoAdquirente'] = 'adquirente';
                break;
            case 'AUT':
                ruta = 'autorizados';
                params['tipoAdquirente'] = 'autorizado';
                break;
            case 'RES':
                ruta = 'responsables';
                params['tipoAdquirente'] = 'responsable';
                break;
            case 'VEN':
                ruta = 'vendedores-ds';
                params['tipoAdquirente'] = 'vendedor';
                break;
            case 'PROV':
                ruta = 'proveedores';
                break;
            case 'DOC':
                ruta = 'documentos';
                break;
            case 'OFE':
                ruta = 'ofe';
                break;
            case 'ACTOR':
                ruta = 'actor';
                break;
            case 'RADIAN':
                ruta = 'radian';
                break;
            case 'RFA':
                ruta = 'resoluciones-facturacion';
                break;
            case 'SPT':
                ruta = 'spt';
                break;
            case 'RECEPCION':
                ruta = 'recepcion';
                break;
            case 'AED':
                ruta = 'autorizaciones-eventos-dian';
                break;
            case 'USUECM':
                ruta = 'usuarios-ecm';
                break;
            case 'DMCARGOS':
                ruta = 'dmcargos';
                break;
            case 'DMDESCUENTOS':
                ruta = 'dmdescuentos';
                break;
            case 'DMPRODUCTOS':
                ruta = 'dmproductos';
                break;
            case 'EMP':
                ruta = 'nomina-electronica/empleadores';
                break;
            case 'TRA':
                ruta = 'nomina-electronica/trabajadores';
                break;
            case 'DN':
                ruta = 'nomina-electronica';
                break;
            case 'GRUPOTRABAJO':
                ruta = 'grupos-trabajo';
                break;
            case 'ASOCIARUSUARIO':
                ruta = 'grupos-trabajo-usuarios';
                break;
            case 'ASOCIARPROVEEDOR':
                ruta = 'grupos-trabajo-proveedores';
                break;
            case 'RECEPCIONANEXOS':
                ruta = 'documentos-anexos';
                break;
            case 'DS':
                ruta = 'documentos-soporte';
                break;
            default:
                break;
        }
        let slug;
        if(ruta === 'documentos'){
            slug = `${this.apiUrl}emision/documentos/lista-errores`; 
        } else if(ruta === 'nomina-electronica'){
            slug = `${this.apiUrl}nomina-electronica/documentos/lista-errores`; 
        } else if(ruta === 'usuarios'){
            slug = `${this.apiUrl}sistema/${ruta}/lista-errores`; 
        } else if(ruta === 'recepcion'){
            slug = `${this.apiUrl}recepcion/documentos/lista-errores`; 
        } else if(ruta === 'documentos-anexos'){
            slug = `${this.apiUrl}recepcion/documentos/documentos-anexos/lista-errores`; 
        }  else if(ruta === 'documentos-soporte'){
            slug = `${this.apiUrl}documento-soporte/documentos/lista-errores`; 
        } else if(ruta === 'dmcargos'){
            slug = `${this.apiUrl}facturacion-web/parametros/cargos/lista-errores`; 
        } else if(ruta === 'dmdescuentos'){
            slug = `${this.apiUrl}facturacion-web/parametros/descuentos/lista-errores`; 
        } else if(ruta === 'dmproductos'){
            slug = `${this.apiUrl}facturacion-web/parametros/productos/lista-errores`; 
        } else if(ruta === 'radian'){
            slug = `${this.apiUrl}radian/lista-errores`;
        } else if(ruta === 'actor'){
            slug = `${this.apiUrl}configuracion/radian/${ruta}/lista-errores`;
        } else {
            slug = `${this.apiUrl}configuracion/${ruta}/lista-errores`;
        }
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Accept', 'application/json');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        headers.set('Pragma', 'no-cache');
        return this.authHttp.post(
            slug,
            params,
            {headers: headers}
        );
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param params
     */
    descargarExcel(params): Observable<any> {
        let ruta;
        switch (params.tipoLog) {
            case 'USU':
            case 'ASOCIARROLES':
                ruta = 'usuarios';
                break;
            case 'ADQ':
                ruta = 'adquirentes';
                params['tipoAdquirente'] = 'adquirente';
                break;
            case 'AUT':
                ruta = 'autorizados';
                params['tipoAdquirente'] = 'autorizado';
                break;
            case 'RES':
                ruta = 'responsables';
                params['tipoAdquirente'] = 'responsable';
                break;
            case 'VEN':
                ruta = 'vendedores-ds';
                params['tipoAdquirente'] = 'vendedor';
                break;
            case 'PROV':
                ruta = 'proveedores';
                break;
            case 'DOC':
                ruta = 'documentos';
                break;
            case 'OFE':
                ruta = 'ofe';
                break;
            case 'ACTOR':
                ruta = 'actor'; 
                break;
            case 'RADIAN':
                ruta = 'radian'; 
                break;
            case 'RFA':
                ruta = 'resoluciones-facturacion';
                break;
            case 'SPT':
                ruta = 'spt';
                break;
            case 'RECEPCION':
                ruta = 'recepcion';
                break;
            case 'AED':
                ruta = 'autorizaciones-eventos-dian';
                break;
            case 'USUECM':
                ruta = 'usuarios-ecm';
                break;
            case 'DMCARGOS':
                ruta = 'dmcargos';
                break;
            case 'DMDESCUENTOS':
                ruta = 'dmdescuentos';
                break;
            case 'DMPRODUCTOS':
                ruta = 'dmproductos';
                break;
            case 'EMP':
                ruta = 'nomina-electronica/empleadores';
                break;
            case 'TRA':
                ruta = 'nomina-electronica/trabajadores';
                break;
            case 'DN':
                ruta = 'nomina-electronica';
                break;
            case 'GRUPOTRABAJO':
                ruta = 'grupos-trabajo';
                break;
            case 'ASOCIARUSUARIO':
                ruta = 'grupos-trabajo-usuarios';
                break;
            case 'ASOCIARPROVEEDOR':
                ruta = 'grupos-trabajo-proveedores';
                break;
            case 'RECEPCIONANEXOS':
                ruta = 'documentos-anexos';
                break;
            case 'DS':
                ruta = 'documentos-soporte';
                break;
            default:
                break;
        }
        let slug;
        if(ruta === 'documentos'){
            slug = `${this.apiUrl}emision/documentos/lista-errores/excel`; 
        } else if(ruta === 'nomina-electronica'){
            slug = `${this.apiUrl}nomina-electronica/documentos/lista-errores/excel`;
        } else if(ruta === 'usuarios'){
            slug = `${this.apiUrl}sistema/${ruta}/lista-errores/excel`; 
        } else if(ruta === 'recepcion'){
            slug = `${this.apiUrl}recepcion/documentos/lista-errores/excel`; 
        } else if(ruta === 'documentos-anexos'){
            slug = `${this.apiUrl}recepcion/documentos/documentos-anexos/lista-errores/excel`; 
        } else if(ruta === 'documentos-soporte'){
            slug = `${this.apiUrl}documento-soporte/documentos/lista-errores/excel`; 
        } else if(ruta === 'dmcargos'){
            slug = `${this.apiUrl}facturacion-web/parametros/cargos/lista-errores/excel`; 
        } else if(ruta === 'dmdescuentos'){
            slug = `${this.apiUrl}facturacion-web/parametros/descuentos/lista-errores/excel`; 
        } else if(ruta === 'dmproductos'){
            slug = `${this.apiUrl}facturacion-web/parametros/productos/lista-errores/excel`; 
        } else if(ruta === 'radian'){
            slug = `${this.apiUrl}radian/lista-errores/excel`;
        } else {
            slug = `${this.apiUrl}configuracion/${ruta}/lista-errores/excel`;
        }
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            slug,
            params,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response'
            }
        ).pipe(
            map(response => this.downloadFile(response)),
            catchError(err => {
            let error = err.message ? err.message : 'Error en la descarga del Excel';
                return throwError(() => new Error(error));
            })
        );
    }
}
