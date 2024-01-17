import {Injectable} from '@angular/core';
import {HttpClient, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {BaseService} from '../../../../core/base.service';

@Injectable()
export class ParametrosService extends BaseService{

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

    set setSlug(slug){
        this.slug  = slug;
    }

    /**
     * Obtiene una lista paginada de registros.
     * 
     * @param params
     */
    listar(params: string): Observable<any> {
        const queryParams = new HttpParams({ fromString: params });

        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/emision/dhl-express/documentos-cco/parametros/${this.slug}/listar`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene un registro.
     * 
     * @param id Identificador del registro
     */
    get(id): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/emision/dhl-express/documentos-cco/parametros/${this.slug}/${id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Realiza una búsqueda predictiva en las parámetricas de openETL, ya sea mediante código o descripción.
     * 
     * @param {string} tabla Tabla paramétrica sobre la cual realizar la búsqueda
     * @param {string} campo Campo de la tabla  paramétrica sobre la cual realizar la búsqueda
     * @param {string} valor Texto de búsqueda
     * @param {number} ofe_id ID del OFE relacionado con la búsqueda, no es obligatorio
     * @param {descripción} ofe_id ID del OFE relacionado con la búsqueda, no es obligatorio
     */
    searchParametricas(tabla, campo, valor, ofe_id = '', descripcion = ''): Observable<any> {
        let top_aplica_para = '';
        if(descripcion.indexOf('Crédito') !== -1)
            top_aplica_para = 'NC';
        else if(descripcion.indexOf('Débito') !== -1)
            top_aplica_para = 'ND';
        else if(descripcion.indexOf('Factura') !== -1)
            top_aplica_para = 'FC';

        let values = {
            tabla : tabla,
            campo : campo,
            valor : valor,
            ofe_id: ofe_id,
            top_aplica_para: top_aplica_para
        }

        return this.authHttp.post(
            `${this.apiUrl}parametros/search-parametricas`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un registro.
     * 
     * @param {string} values Valores a registrar
     * @param {number} id Identificador del registro
     */
    update(values, id): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}proyectos-especiales/emision/dhl-express/documentos-cco/parametros/${this.slug}/${id}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo registro.
     * 
     * @param {object} values Valores a registrar
     */
     create(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/emision/dhl-express/documentos-cco/parametros/${this.slug}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de registros en lote.
     * 
     * @param {object} values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/emision/dhl-express/documentos-cco/parametros/${this.slug}/cambiar-estado`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Retorna una colección de paramétricas del proyecto especial Pickup Cash.
     * 
     * @param {object} params string 
     */
    getParametrosProyectoEspecialPickupCash(params: string = ''): Observable<any> {
        let queryParams = null;
        if(params)
            queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}proyectos-especiales/emision/dhl-express/documentos-cco/parametros/get-parametros-pickup-cash`,
            { headers: this.getHeaders(), params: queryParams }
        );
    }

    /**
     * Realiza una búsqueda predictiva en la tabla de productos Pickup Cash, ya sea mediante código o descripción uno o descripción dos.
     * 
     * @param {string} valor Texto de búsqueda
     */
    searchProductosPickupCash(valor): Observable<any> {
        let values = {
            valor: valor
        }

        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/emision/dhl-express/documentos-cco/parametros/productos/search-productos-pickup-cash`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Consulta una Guía de DHL Express en el sistema.
     * 
     * Aplica para DHL Express, documentos del proceso Pickup Cash
     * 
     * @param guia
     */
    verificarGuiaDhlExpress(guia): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/emision/dhl-express/documentos-cco/consultar-guia`,
            this._parseObject({ guia: guia }),
            { headers: this.getHeaders() }
        )
    }
}
