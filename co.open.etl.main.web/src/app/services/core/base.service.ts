import {environment} from '../../../environments/environment';
import {HttpHeaders} from '@angular/common/http';
import {Observable} from 'rxjs';

export class BaseService {
    public apiUrl = environment.API_ENDPOINT;
    public apiDIUrl = environment.API_DI;
    public apiDOUrl = environment.API_DO;
    private headers = new HttpHeaders();

    constructor() {
        this.headers = this.headers.set('Content-type', 'application/x-www-form-urlencoded');
        this.headers = this.headers.set('X-Requested-Whith', 'XMLHttpRequest');
        this.headers = this.headers.set('Accept', 'application/json');
        this.headers = this.headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        this.headers = this.headers.set('Pragma', 'no-cache');
        this.headers = this.headers.set('Expires', '0');
    }

    /**
     * Concatena un array con ,
     * @param array
     */
    public join(array) {
        let cad = '';
        for (let i = 0; i < array.length; i++) {
            if ( i === 0)
                cad = array[i];
            else
                cad = cad + ',' + array[i];
        }
        return cad;
    }

    /**
     * Limpia los valores vacios de un array
     * @param array
     */
    public clearEmpty(array) {
        var filtered = array.filter(function (el) {
            return el != null;
        });
        return filtered;
    }

    /**
     * Devuelve las cabeceras para las peticiones http.
     * 
     */
    public getHeaders(): HttpHeaders {
        return this.headers;
    }

    /**
     * Mapeo de la respuesta
     * @param object
     * @private
     */
    public _parseObject(object: any) {
        return Object.keys(object).map(
            k => object[k] ? `${encodeURIComponent(k)}=${encodeURIComponent(object[k])}` : `${encodeURIComponent(k)}=`
        ).join('&');
    }

    /**
     * Obtiene el nombre del archivo de la cabecera content disposition
     * @param contentDisposition
     */
    public getNombreArchivo(contentDisposition) {
        const NOMBRE = contentDisposition.split(';')[1].trim().split('=')[1];
        return NOMBRE.replace(/"/g, '');
    }

    /**
     * Se encarga de procesar una respuesta para la descarga de archivos
     * @param data Contenido Blob del archivo
     */
    public downloadFile(data: any) {
        const ARCHIVO   = this.getNombreArchivo(data.headers.get('Content-Disposition'));
        var contentType = data.headers.get('Content-Type');

        if(contentType == 'application/pdf')
            contentType = 'application/octet-stream';

        const BLOB = new Blob([data.body], {type: contentType});
        const URL  = window.URL.createObjectURL(BLOB);

        const a    = document.createElement('a');
        a.href     = URL;
        a.target   = "_blank";
        a.download = ARCHIVO;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        window.URL.revokeObjectURL(URL);
    }

    /**
     * Parsea errores recibidos durante peticiones HTTP
     *
     * @param object error
     * @returns string
     */
    public parseError(error) {
        let errores = '';
        if (typeof error.errors !== undefined && typeof error.errors === 'object') {
            let index = Object.keys(error.errors);
            if (index[0] !== '0') {
                if (error.errors[index[0]].length > 1) {
                    errores = '<ul>';
                    error.errors[index[0]].forEach(strError => {
                        errores += '<li>' + strError + '</li>';
                    });
                    errores += '</ul>';
                } else {
                    errores = error.errors[index[0]][0];
                }
            } else {
                if (error.errors.length > 1) {
                    errores = '<ul>';
                    error.errors.forEach(strError => {
                        errores += '<li>' + strError + '</li>';
                    });
                    errores += '</ul>';
                } else {
                    errores = error.errors[0];
                }
            }

        } else if (typeof error.message !== undefined && error.status_code !== 500) {
            errores = error.message;
        } else {
            errores = 'Se produjo un error al procesar la información.';
        }
        if (errores === undefined && error.message === undefined) {
            errores = 'NO fue posible realizar la operación solicitada';
        }
        return errores;
    }

     /**
     * Devuelve las cabeceras para las peticiones http con Content-Type: Application JSON.
     * 
     */
    public getHeadersApplicationJSON(): HttpHeaders {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Accept', 'application/json');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        headers.set('Pragma', 'no-cache');
        
        return headers;
    }

    /**
     * Permite cambiar el estado de documentos enviados y sin envío
     * @param cdoIds
     */
    cambiarEstadoDocumentos(authHttp, cdoIds: any, tipoEnvio: any): Observable<any> {
        return authHttp.post(
            `${this.apiUrl}emision/documentos/cambiar-estado-documentos`,
            {
                cdoIds: cdoIds,
                tipoEnvio: tipoEnvio
            },
            { headers: this.getHeadersApplicationJSON() }
        )
    }

    /**
     * Convierte una cadeba a snake_case y elimina caracteres especiales.
     * 
     * @param {string} cadena Cadena a sanitizar
     * @returns {string}
     */
    sanitizarString(cadena: string) {
        return cadena
            .normalize('NFD')
            .replace(/([\u0300-\u036f]|[^0-9a-zA-Z ])/g, '')
            .match(/[A-Z]{2,}(?=[A-Z][a-z]+[0-9]*|\b)|[A-Z]?[a-z]+[0-9]*|[A-Z]|[0-9]+/g)
            .map(x => x.toLowerCase())
            .join('_')
    }
}
