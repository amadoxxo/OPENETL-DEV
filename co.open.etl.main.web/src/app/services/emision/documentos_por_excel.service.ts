import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosPorExcelService extends BaseService{

    // Usuario en línea
    public usuario: any;
    public slug: string;

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    generarInterfaceFacturas(ofe_id) {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}emision/documentos/generar-interface-facturas/${ofe_id}`,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }

    generarInterfaceNotasCreditoDebito(ofe_id) {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}emision/documentos/generar-interface-notas-credito-debito/${ofe_id}`,
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
     * Permite la carga de Documentos Electrónicos (Facturas y Notas Crédito / Notas Débito) en Emisión.
     *
     * @param ofe_id Id del Oferente
     * @param fileToUpload any Documento manual a cargar
     * @param evento string Tipo de archivo a cargar
     * @param ofe_identificacion
     * @param documentoReferencia object Objeto con la información del documento referencia
     * @returns {Observable<any>}
     */
    cargarDocumentosPorExcel(ofe_id, fileToUpload: any, evento: string, ofe_identificacion: string, documentoReferencia: object = undefined): Observable<any> {
        let slug;
        const INPUT = new FormData();
        INPUT.append('archivo', fileToUpload);
        INPUT.append('ofe_id', ofe_id);

        if (evento === 'subir_fc') {
            if (ofe_identificacion === '860007538')
                slug = 'documentos/cargar-factura-fnc';
            else
                slug = 'documentos/cargar-factura';
        }

        if (evento === 'subir_nc') {
            if (ofe_identificacion === '860007538')
                slug = 'documentos/cargar-nd-nc-fnc';
            else
                slug = 'documentos/cargar-nd-nc';
        }

        // DHL Aero Expreso
        if (ofe_identificacion === '900749828' && (evento === 'subirDa_fc' || evento === 'subirDa_nc' || evento === 'subirDa_nd')) {
            INPUT.append('tipo_documento_electronico', evento.replace('subirDa_', '').toUpperCase());
            slug = 'documentos/cargar-documento-electronico-dhl-aero-expreso';

            if(documentoReferencia !== undefined && documentoReferencia !== null) {
                INPUT.append('documento_referencia', JSON.stringify(documentoReferencia));
            }
        }

        // DHL Express - Pickup Cash
        if (ofe_identificacion === '860502609' && evento === 'subir_pickup_cash') {
            slug = 'documentos/cargar-archivo-pickup-cash-dhl-express';
        }

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiDIUrl}${slug}`,
            INPUT,
            { headers: headers }
        );
    }

    /**
     * Descarga la consulta de registros en un archivo de Excel.
     * 
     * @param buscar
     */
    descargarExcelPost(buscar: string): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('buscar', buscar);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiUrl}descargar-excel-${this.slug}`,
            INPUT,
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

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param {object} params - parámetros de la petición
     * @returns {Observable<any>}
     */
    descargarExcelGet(params): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}configuracion/lista-${this.slug}`,
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
     * Permite el envío al backend, de los archivos cargados por FITAC
     * en el cargue manual de documentos.
     *
     * @param {number} ofe_id - ID del Ofe
     * @param {object} archivoDatos - Archivo de excel con los datos de los documentos
     * @param {object} archivoFechas - Archivo de excel con las fechas de vencimiento de los documentos
     * @param {string} accion - Accion a ejecutar (Cargar FC o NC/ND)
     * @returns {Observable<any>}
     */
    cargaManualFitac(ofe_id, archivoDatos: any, archivoFechas: any, accion): Observable<any> {
        let tipoDoc = '';
        if(accion === 'subir_fc') {
            tipoDoc = 'FC';
        } else {
            tipoDoc = 'ND-NC';
        }

        const INPUT = new FormData();
        INPUT.append('ofe_id', ofe_id);
        INPUT.append('archivoDatos', archivoDatos);
        INPUT.append('archivoFechas', archivoFechas);
        INPUT.append('tipoDoc', tipoDoc);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/cargar-documentos-fitac`,
            INPUT,
            { headers: headers }
        );
    }

    /**
     * Obtiene el listado de Errores para el tracking.
     * 
     */
    getListaLogErrores(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Accept', 'application/json');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        headers.set('Pragma', 'no-cache');
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/lista-errores`,
            params,
            {headers: headers}
        );
    }

    descargarExcel(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}emision/documentos/lista-errores/excel`,
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

    /**
     * Realiza una búsqueda de un documento referencia.
     * 
     *  @param string ofe_identificacion Identificacion del OFE
     *  @param string tipo Código del tipo de documento electrónico
     *  @param string prefijo Prefijo del documento a buscar
     *  @param string consecutivo Consecutivo del documento a buscar
     */
    searchDocumentoReferencia(ofe_identificacion, tipo, prefijo, consecutivo): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}emision/documentos/consultar-documento/ofe/${ofe_identificacion}/tipo/${tipo}/prefijo/${prefijo}/consecutivo/${consecutivo}`,
            {headers: this.getHeaders()}
        );
    }
}
