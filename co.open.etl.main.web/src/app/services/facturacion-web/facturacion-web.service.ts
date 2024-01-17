import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from "rxjs";
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class FacturacionWebService extends BaseService {
    // Usuario en línea
    public usuario   : any;
    public aplicaPara: string = '';

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor(public authHttp: HttpClient) {
        super();
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param {string} params Parámetros a enviar
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    descargarExcel(params: string, origen: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        let headers = new HttpHeaders();
        headers     = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers     = headers.set('Pragma', 'no-cache');
        headers     = headers.set('Expires', '0');

        return this.authHttp.get<Blob>(
            `${this.apiUrl}facturacion-web/parametros/lista-${origen}`,
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
     * Obtiene una lista paginada de registros.
     * 
     * @param {string} params Parámetros a enviar
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    listarRegistros(params: string, origen: string): Observable<any> {
        const queryParams = new HttpParams({ fromString: params });

        return this.authHttp.get(
            `${this.apiUrl}facturacion-web/parametros/lista-${origen}`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de registros en lote.
     * 
     * @param {array} values IDs de los registros a cambiar de estado
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    cambiarEstadoRegistros(values: Array<any>, origen: string): Observable<any> {
        let object: any = {'codigos': values.join(',')};

        if(origen === 'cargos')
            object = {'dmc_ids': values.join(',')};
        if(origen === 'descuentos')
            object = {'dmd_ids': values.join(',')};
        if(origen === 'productos')
            object = {'dmp_ids': values.join(',')};

        return this.authHttp.post(
            `${this.apiUrl}facturacion-web/parametros/${origen}/cambiar-estado`,
            this._parseObject(object),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Crea un nuevo registro.
     * 
     * @param {any} values Valores a registrar
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    createRegistro(values: any, origen: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}facturacion-web/parametros/${origen}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un registro.
     * 
     * @param {any} values Valores a registrar
     * @param {number} id Identificador del registro
     * @param {string} origen Origen de la petición (control-consecutivos, cargos, descuentos o productos)
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    updateRegistro(values: any, id: number, origen: string): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}facturacion-web/parametros/${origen}/${id}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la información de un registro.
     * 
     * @param {any} values Valores a registrar
     * @returns {Observable<any>}
     * @memberof FacturacionWebService
     */
    getProducto(values: any, origen: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}facturacion-web/parametros/${origen}/consultar-producto`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Setter para asignar el aplica para.
     *
     * @memberof FacturacionWebService
     */
    set setAplicaPara(aplicaPara){
        this.aplicaPara  = aplicaPara;
    }

    /**
     * Obtiene los ofes asociados al usuario seleccionado.
     *
     * @param {*} valor Valor a buscar
     * @param {*} ofe_id Id del OFE seleccionado
     * @return {*}  {Observable<any>}
     * @memberof FacturacionWebService
     */
    obtenerProductos(valor, ofe_id): Observable<any> {
        let params = (this.aplicaPara !== '') ? 'aplica_para=' + this.aplicaPara : '';
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}facturacion-web/parametros/productos/buscar-ng-select/${valor}/ofe/${ofe_id}`,
            { 
                headers: this.getHeaders(),
                params : queryParams
            }
        );
    }

    /**
     * Obtiene los cargos asociados al oferente.
     *
     * @param {*} valor valor a buscar
     * @param {*} ofe_id Id del OFE seleccionado
     * @return {*}  {Observable<any>}
     * @memberof CommonsService
     */
    obtenerCargos(valor, ofe_id): Observable<any> {
        let params = (this.aplicaPara !== '') ? 'aplica_para=' + this.aplicaPara : '';
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}facturacion-web/parametros/cargos/buscar-ng-select/${valor}/ofe/${ofe_id}`,
            { 
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene los descuentos asociados al oferente.
     *
     * @param {*} valor valor a buscar
     * @param {*} ofe_id Id del OFE seleccionado
     * @return {*}  {Observable<any>}
     * @memberof CommonsService
     */
    obtenerDescuentos(valor, ofe_id): Observable<any> {
        let params = (this.aplicaPara !== '') ? 'aplica_para=' + this.aplicaPara : '';
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}facturacion-web/parametros/descuentos/buscar-ng-select/${valor}/ofe/${ofe_id}`,
            { 
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene los conceptos de correción asociados al documento.
     *
     * @param {*} valor Valor a buscar
     * @param {*} tipoDocumento Tipo de documento a filtrar
     * @return {*}  {Observable<any>}
     * @memberof CommonsService
     */
    obtenerConceptosCorreccion(valor, tipo, ofe_id ='0'): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/concepto-correccion/buscar-ng-select/${valor}/tipo/${tipo}/ofe/${ofe_id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene los documentos de referencia para las notas crédito y débito.
     *
     * @param {*} values Valores a buscar
     * @return {*}  {Observable<any>}
     * @memberof FacturacionWebService
     */
    obtenerDocumentosReferencia(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/consultar-documentos-referencia`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la información de un documento referencia en específico.
     *
     * @param {*} values Valores a buscar
     * @return {*}  {Observable<any>}
     * @memberof FacturacionWebService
     */
    obtenerDocumentoElectronicoSeleccionado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/consultar-data-documento-electronico`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Realiza la descarga de la vista previa de las Representaciones Gráficas.
     * 
     *  @param json_documento json del documento de facturación web
     */
    vistaPreviaRG(json_documento: any): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiDOUrl}pdf/facturacion-web/ver-representacion-grafica-documento`,
            json_documento,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        )
        .pipe(
            map((response) => {
                if (response.status === 200)
                    this.downloadFile(response);
            }),
            catchError(err => {
                let error = err.message ? err.message : 'Error en la descarga de la vista previa de la RG';
                return throwError(() => new Error(error));
            })
        );
    }

    /**
     * Envía el json de un documento electrónico de facturación web para ser creado en el sistema.
     *
     * @param {*} jsonDocumento json del documento de facturación web
     * @return {*}  {Observable<any>}
     * @memberof FacturacionWebService
     */
    enviarDocumentoElectronicoManual(jsonDocumento): Observable<any> {
        return this.authHttp.post(
            `${this.apiDIUrl}registrar-documentos`,
            jsonDocumento,
            { headers: this.getHeadersApplicationJSON() }
        )
    }
}