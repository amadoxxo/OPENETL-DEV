import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';
import {Pais, PaisesInteface} from '../../main/models/pais.model';
import {CodigoPostal, CodigoPostalInteface} from '../../main/models/codigo-postal.model';
import {Municipio, MunicipioInteface} from '../../main/models/municipio.model';
import {Departamento, DepartamentoInteface} from '../../main/models/departamento.model';

@Injectable()
export class ParametrosService extends BaseService{

    // Usuario en línea
    public usuario      : any;
    public slug         : string;
    public slugRadian   : string;
    public aplicaPara   : any = '';

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    set setSlug(slug){
        this.slug  = slug;
    }

    set setSlugRadian(slugRadian){
        this.slugRadian  = slugRadian;
    }

    set setAplicaPara(aplicaPara){
        this.aplicaPara  = aplicaPara;
    }

    /**
     * Obtiene un registro.
     * 
     * @param id Identificador del registro
     */
    get(id): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/${this.slug}/${id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene un registro de roles eventos.
     * 
     * @param id Identificador del registro
     */
    getRolesEventos(id): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/radian/roles-eventos/${id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo registro.
     * 
     * @param values Valores a registrar
     */
    create(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/${this.slug}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un registro.
     * 
     * @param values Valores a registrar
     * @param id Identificador del registro
     */
    update(values, id): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}parametros/${this.slug}/${id}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de registros.
     * 
     * @param params
     */
    listar(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}parametros/lista-${this.slug}`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene una lista de registros de Radian.
     *
     * @param {string} params Parámetros de la petición
     * @return {*}  {Observable<any>}
     * @memberof ParametrosService
     */
    listarRadian(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}parametros/radian/lista-${this.slugRadian}`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene registros para poblar select.
     * 
     * @param id Identificador del registro
     */
    listarSelect(ruta): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/${ruta}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Elimina un registro.
     * 
     * @param id
     */
    delete(id): Observable<any> {
        return this.authHttp.delete(
            `${this.apiUrl}parametros/${this.slug}/${id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de registros en lote.
     * 
     * @param values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/${this.slug}/cambiar-estado`,
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
            `${this.apiUrl}parametros/${this.slug}/cambiar-estado`,
            values,
            { headers: this.getHeadersApplicationJSON() }
        )
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
     * @returns
     */
    descargarExcelGet(params): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}parametros/lista-${this.slug}`,
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
     * Permite la descarga en excel de una consulta en pantalla para Radian.
     *
     * @param {string} params Parámetros de la petición
     * @return {*}  {Observable<any>}
     * @memberof ParametrosService
     */
    descargarExcelRadianGet(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}parametros/radian/lista-${this.slugRadian}`,
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
     * Obtiene el registro de una paramétrica a partir de su código y fechas de vigencia.
     * 
     * @param values
     */
    consultaRegistroParametrica(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}parametros/${this.slug}/consulta-registro-parametrica`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }

    //**************** ENDPOINTS REFERENTES A PAÍSES, DEPARTAMENTOS Y MUNICIPIOS ***************

    /**
     * Realiza una búsqueda predictiva de países dada su descripción o código desde un NgSelect
     */
    searchPaisesNgSelect(valor): Observable<Pais[]> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-paises/${valor}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: PaisesInteface) => rsp.data));
    }

    /**
     * Realiza una búsqueda predictiva de departamentos dada su descripción o código desde un NgSelect.
     * 
     */
    searchDepartamentoNgSelect(valor, pai_id): Observable<Departamento[]> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-departamentos/${valor}/pais/${pai_id}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: DepartamentoInteface) => rsp.data));
    }

    /**
     * Realiza una búsqueda predictiva de municipios dada su descripción o código desde un NgSelect.
     * 
     */
    searchMunicipioNgSelect(valor, dep_id): Observable<Municipio[]> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-municipio/${valor}/departamento/${dep_id}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: MunicipioInteface) => rsp.data));
    }

    /**
     * Realiza una búsqueda predictiva de códigos postales desde un NgSelect.
     * 
     */
    searchCodigosPostalesNgSelect(valor): Observable<CodigoPostal[]> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/codigo-postal/buscar-ng-select/${valor}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: CodigoPostalInteface) => rsp.data));
    }

    /**
     * Realiza una búsqueda predictiva de países dada su descripción o código.
     * 
     * @param valor Descripción del País a buscar
     */
    searchPaises(valor): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-paises/${valor}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda predictiva de Eventos Documentos Electronicos dada su descripción o código.
     * 
     * @param {string} valor Descripción del País a buscar
     */
    searchEventosDocumentosElectronicos(valor: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/radian/search-evento-documento-electronico/${valor}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda predictiva de departamentos dada su descripción o código.
     * 
     * @param des Descripción del Departamento a buscar
     */
    searchDepartamentos(valor, paisId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-departamentos/${valor}/pais/${paisId}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda de un departamento dada su descripción
     * 
     * @param des Descripción del Departamento a buscar
     */
    buscarDepartamentoExacto(dep, pais): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/departamentos/dep_descripcion/valor/${dep}/pais/${pais}/filtro/exacto`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda de un país dada su descripción
     * 
     * @param des Descripción del País a buscar
     */
    buscarPaisExacto(paisDesc, paisId): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/paises/pai_descripcion/valor/${paisDesc}/filtro/exacto`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda de un Evento de Documento Electronico dada su descripción
     * 
     * @param {string} eventoDesc Descripción del País a buscar
     */
    buscarEventoDocumentoElectronicoExacto(eventoDesc: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/radian/evento-documento-electronico/ede_descripcion/valor/${eventoDesc}/filtro/exacto`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Obtiene los países.
     * 
     */
    getPaises(): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/paises`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Retorna coincidencias en la paramétrica de códigos de unidades de medida.
     *
     * @param valor Valor con el que se buscarán coincidencias en la paramétrica
     */
    searchUnidadesMedidas(valor): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-unidades/${valor}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Retorna coincidencias en la paramétrica de códigos de descuento.

     * @param valor Valor con el que se buscarán coincidencias en la paramétrica
     */
    searchCodigosDescuento(valor): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-codigos-descuento/${valor}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Realiza una búsqueda predictiva en las parámetricas de openETL, ya sea mediante código o descripción.
     * 
     * @param {string} tabla Tabla paramétrica sobre la cual realizar la búsqueda
     * @param {string} campo Campo de la tabla  paramétrica sobre la cual realizar la búsqueda
     * @param {string} valor Texto de búsqueda
     * @param {number} ofe_id ID del OFE relacionado con la búsqueda, no es obligatorio
     * @param {string} descripcion Descripción relacionada con el texto a buscar
     * @return {*} {Observable<any>}
     * @memberof ParametrosService
     */
    searchParametricas(tabla: string, campo: string, valor, ofe_id: number = null, descripcion: string = ''): Observable<any> {
        let top_aplica_para = '';
        if(descripcion.indexOf('Crédito') !== -1)
            top_aplica_para = 'NC';
        else if(descripcion.indexOf('Débito') !== -1)
            top_aplica_para = 'ND';
        else if(descripcion.indexOf('Factura') !== -1)
            top_aplica_para = 'FC';

        let tri_tipo = '';
        if(descripcion === 'TRIBUTO' || descripcion === 'TRIBUTO-UNIDAD' || descripcion === 'RETENCION' || descripcion == 'RETENCION-SUGERIDA')
            tri_tipo = (descripcion == 'RETENCION-SUGERIDA') ? 'RETENCION' : descripcion;

        let values = {
            tabla : tabla,
            campo : campo,
            valor : valor,
            ofe_id: ofe_id,
            top_aplica_para: top_aplica_para,
            tri_tipo: tri_tipo,
            aplica_para: this.aplicaPara,
            retencion_sugerida: (descripcion == 'RETENCION-SUGERIDA') ? 'SI' : ''
        }

        return this.authHttp.post(
            `${this.apiUrl}parametros/search-parametricas`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }
}
