import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';
import {GrupoTrabajo, GrupoTrabajoInteface} from '../../main/models/grupo-trabajo.model';
import {Trabajador, TrabajadorInteface} from '../../main/models/trabajador.model';
import {ResponsabilidadFiscal, ResponsabilidadFiscalInteface} from '../../main/models/responsabilidad-fiscal.model';
import {UsuarioInteface} from '../../main/models/usuario.model';

interface UsuariosInterface {
    usuarios
};
@Injectable()
export class ConfiguracionService extends BaseService{

    // Usuario en línea
    public usuario   : any;
    public slug      : string;
    public slugRadian: string;

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

    /**
     * Obtiene un registro.
     * 
     * @param id Identificador del registro
     */
    get(id): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/${this.slug}/${id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene un registro de adquirentes.
     * 
     * @param adq_identificacion Identificador del adquirente
     * @param ofe_identificacion Identificador del OFE
     * @param adq_id_personalizado ID Personalizado del adquirente
     */
    getAdq(adq_identificacion, ofe_identificacion, adq_id_personalizado = null): Observable<any> {
        let adqIdPersonalizado = '';
        if(adq_id_personalizado !== '' && adq_id_personalizado !== null && adq_id_personalizado !== undefined)
            adqIdPersonalizado = `/${adq_id_personalizado}`;

        return this.authHttp.get(
            `${this.apiUrl}configuracion/${this.slug}/${ofe_identificacion}/${adq_identificacion}` + adqIdPersonalizado,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene un registro de proveedores.
     * 
     * @param pro_identificacion Identificador del proveedor
     * @param ofe_identificacion Identificador del OFE
     */
    getProv(pro_identificacion, ofe_identificacion): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/${this.slug}/${ofe_identificacion}/${pro_identificacion}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene un registro de un grupo de trabajo.
     * 
     * @param codigo_grupo Código del grupo de trabajo
     * @param ofe_identificacion Identificador del OFE
     */
    getGrp(codigo_grupo, ofe_identificacion): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/${this.slug}/${ofe_identificacion}/${codigo_grupo}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene un registro de un grupo de trabajo.
     * 
     * @param gtr_codigo Código del grupo de trabajo
     * @param ofe_identificacion Identificador del OFE
     * @param usu_email Email del Usuario
     */
    getGrupoTrabajoAsociado(gtr_codigo, ofe_identificacion, usu_email): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/${this.slug}/${gtr_codigo}/${ofe_identificacion}/${usu_email}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo registro.
     * 
     * @param payload Valores a registrar
     */
    create(payload): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}configuracion/${this.slug}`,
            payload,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Actualiza un registro.
     * 
     * @param payload Valores a registrar
     * @param id Identificador del registro
     */
    update(payload, id): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}configuracion/${this.slug}/${id}`,
            payload,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Actualiza un registro de adquirentes.
     * 
     * @param payload Valores a registrar
     * @param adq_identificacion Identificador del adquirente
     * @param ofe_identificacion Identificador del OFE
     * @param adq_id_personalizado ID Personalizado del adquirente
     */
    updateAdq(payload, adq_identificacion, ofe_identificacion, adq_id_personalizado = null): Observable<any> {
        let adqIdPersonalizado = '';
        if(adq_id_personalizado !== '' && adq_id_personalizado !== null && adq_id_personalizado !== undefined)
            adqIdPersonalizado = `/${adq_id_personalizado}`;

        return this.authHttp.put(
            `${this.apiUrl}configuracion/${this.slug}/${ofe_identificacion}/${adq_identificacion}` + adqIdPersonalizado,
            payload,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Actualiza un registro de proveedores.
     * 
     * @param payload Valores a registrar
     * @param pro_identificacion Identificador del proveedor
     * @param ofe_identificacion Identificador del OFE
     */
    updateProveedor(payload, pro_identificacion, ofe_identificacion): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}configuracion/${this.slug}/${ofe_identificacion}/${pro_identificacion}`,
            payload,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Actualiza un registro de grupos de trabajo.
     * 
     * @param payload Valores a registrar
     * @param ofe_identificacion Identificador del OFE
     * @param codigo_grupo Código del grupo de trabajo
     */
    updateGrupoTrabajo(payload, ofe_identificacion, codigo_grupo): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}configuracion/${this.slug}/${ofe_identificacion}/${codigo_grupo}`,
            payload,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Actualiza un registro de grupos de trabajo usuarios asociados.
     * 
     * @param payload Valores a registrar
     * @param gtr_codigo Código del grupo de trabajo
     * @param ofe_identificacion Identificador del OFE
     * @param usu_email Email del Usuario
     */
    updateGrupoTrabajoAsociarUsuario(payload, gtr_codigo, ofe_identificacion, usu_email): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}configuracion/${this.slug}/${gtr_codigo}/${ofe_identificacion}/${usu_email}`,
            payload,
            { headers: this.getHeadersApplicationJSON() }
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
            `${this.apiUrl}configuracion/lista-${this.slug}`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene una lista de registros para Radian.
     *
     * @param {string} params Parámetros de la petición
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    listarRadian(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}configuracion/radian/lista-${this.slugRadian}`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene una lista de los usuarios asociados a un grupo de trabajo.
     * 
     * @param params Parámetros de búsqueda
     */
    listarUsuariosAsociados(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}configuracion/grupos-trabajo-usuarios/lista-usuarios-asociados`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene una lista de los grupos de trabajo con los cuales esta asociado un usuario.
     * 
     * @param {string} tipoAsociacion Tipo de asociación del usuario con los grupos de trabajo (gestor-validador)
     */
    obtenerGruposTrabajoUsuario(tipoAsociacion: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}configuracion/grupos-trabajo-usuarios/grupos-trabajo`,
            this._parseObject({'tipo_asociacion': tipoAsociacion}),
            {
                headers: this.getHeaders()
            }
        );
    }

    /**
     * Obtiene una lista de los proveedores asociados a un grupo de trabajo.
     * 
     * @param params Parámetros de búsqueda
     */
    listarProveedoresAsociados(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}configuracion/grupos-trabajo-proveedores/lista-proveedores-asociados`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene una lista de los grupos de trabajo asociados a un proveedor.
     *
     * @param {string} params Parámetros de búsqueda
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    listarGruposTrabajoProveedor(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}configuracion/grupos-trabajo-proveedores/lista-grupos-trabajo-proveedor`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene registros para poblar select.
     * 
     * @param ruta Ruta del recurso a obtener
     */
    listarSelect(ruta): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/${ruta}`,
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
            `${this.apiUrl}configuracion/${this.slug}/${id}`,
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
            `${this.apiUrl}configuracion/${this.slug}/cambiar-estado`,
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
     * Permite la descarga en excel de una consulta en pantalla de Radian.
     *
     * @param {string} params Parámetros de la petición
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    descargarExcelRadianGet(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}configuracion/radian/lista-${this.slugRadian}`,
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
     * Realiza una búsqueda predictiva de Responsabilidades Fiscales dada su descripción o código desde un NgSelect
     */
    searchResFiscalNgSelect(valor): Observable<ResponsabilidadFiscal[]> {
        return this.authHttp.get(
            `${this.apiUrl}parametros/search-responsabilidades-fiscales/${valor}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: ResponsabilidadFiscalInteface) => rsp.data));
    }

    /**
     * Comprueba si el Adquirente ya está registrado en el sistema.
     * 
     * @param ofe_identificacion string Identificación del ofe
     * @param identificacion string Identificación del adquirente
     * @param adq_id_personalizado string Id Personalizado del adquirente
     */
    checkIfAdqExists(ofe_identificacion, identificacion: string, adq_id_personalizado): Observable<any> {
        let adqIdPersonalizado = '';
        if(adq_id_personalizado !== '' && adq_id_personalizado !== null && adq_id_personalizado !== undefined)
            adqIdPersonalizado = `&adq_id_personalizado=${adq_id_personalizado}`;
            
        return this.authHttp.get(
            `${this.apiUrl}configuracion/check-adq-identificacion/${ofe_identificacion}?identificacion=${identificacion}` + adqIdPersonalizado,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Comprueba si el Proveedor ya está registrado en el sistema.
     * 
     * @param ofe_identificacion string Identificación del ofe
     * @param identificacion string Identificación del proveedor
     */
    checkIfProvExists(ofe_identificacion, identificacion: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/check-proveedor-identificacion/${ofe_identificacion}?identificacion=${identificacion}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Configura el tipo de Adquirente enviado como parametro en 'SI'.
     * 
     */
    editarTipoAdquirente(tipo, adq_id): Observable<any> {
        const CAMPOS = {
            'tipo': tipo,
            'adq_id': adq_id
        };

        return this.authHttp.post(
            `${this.apiUrl}configuracion/adquirentes/editar-tipo-adquirente`,
            this._parseObject(CAMPOS),
            {headers: this.getHeaders()}
        );
    }

    getPredictiveUsers = (ofe_id: number, text: string) => {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/proveedores/obtener-usuarios/${ofe_id}/${text}`,
            {
                headers: this.getHeaders(),
            }
        )
        .pipe(
            map((data: UsuariosInterface) => {
                return data.usuarios;
            })
        );
    }

    /**
     * Permite buscar coincidencias de proveedores para un OFE en específico.
     * 
     * @param params Parámetros de búsqueda
     */
    searchProveedores(value: string, ofe_id: string): Observable<any> {
        let params = 'buscar=' + value + '&' +
        'ofe_id=' + ofe_id;
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}configuracion/proveedores/search-proveedores`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Permite buscar coincidencias de grupos de trabajo para un OFE en específico.
     * 
     * @param params Parámetros de búsqueda
     */
    searchGruposTrabajo(value: string, ofe_id: string, ofe_identificacion: string = ''): Observable<GrupoTrabajo[]> {
        let params = '';
        if(ofe_id)
            params = 'buscar=' + value + '&' + 'ofe_id=' + ofe_id;
        else if(ofe_identificacion)
            params = 'buscar=' + value + '&' + 'ofe_identificacion=' + ofe_identificacion;

        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}configuracion/grupos-trabajo/search-grupos-trabajo`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        ).pipe(map((rsp: GrupoTrabajoInteface) => rsp.data));
    }

    /**
     * Permite hacer la petición para consultar los usuarios de Usuarios Autorizados Eventos DIAN.
     *
     * @param {string} valorBuscar Valor a buscar
     * @param {string} consultasAdicionales String json que puede contener pares modelo => columna|valor y que permite realizar búsquedas de registros en diferentes
     * tablas del sistema para relacionar data contra los usuarios filtrados
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    searchUsuarios(valorBuscar: string, consultasAdicionales: string = ''): Observable<any> {
        let rutaConsultaAdicional = '';
        if (consultasAdicionales !== '' && consultasAdicionales !== null && consultasAdicionales !== undefined)
            rutaConsultaAdicional = `/${consultasAdicionales}`;

        return this.authHttp.get(
            `${this.apiUrl}sistema/buscar-usuarios/${valorBuscar}` + rutaConsultaAdicional,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Obtiene los ofes asociados al usuario seleccionado.
     *
     * @param {*} usu_identificacion Identificación del OFE seleccionado
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    consultaOfes(usu_identificacion): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/lista-ofes/${usu_identificacion}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene los roles de openECM.
     * 
     * @param ofe_identificacion Identificación del OFE seleccionado
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    obtenerRolesEcm(ofe_identificacion): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/lista-roles-ecm/${ofe_identificacion}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de los registros de empleadores.
     *
     * @param {string} params Parámetros para retornar la lista
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    listarEmpleadores(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}configuracion/nomina-electronica/lista-empleadores`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla de Empleadores.
     *
     * @param {string} params Parámetros para realizar la descarga
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    descargarExcelGetEmpleadores(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}configuracion/nomina-electronica/lista-empleadores`,
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
     * Permite hacer la petición para consultar los empleadores de nómina electrónica.
     *
     * @param {string} valorBuscar Valor a buscar
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    searchEmpleadores(valorBuscar: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/nomina-electronica/buscar-empleadores/${valorBuscar}`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Obtiene una lista de los registros de trabajadores.
     * 
     * @param {string} params Parámetros para retornar la lista
     * @return {Observable<any>}
     * @memberof ConfiguracionService
     */
    listarTrabajadores(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        return this.authHttp.get(
            `${this.apiUrl}configuracion/nomina-electronica/lista-trabajadores`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene un registro de trabajador.
     *
     * @param {number} tra_identificacion Identificador del trabajador
     * @param {number} emp_identificacion Identificador del empleador
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    getTrabajador(tra_identificacion: number, emp_identificacion: number): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/nomina-electronica/trabajadores/${emp_identificacion}/${tra_identificacion}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un registro de trabajador.
     * 
     * @param {*} payload Valores a registrar
     * @param {number} tra_identificacion Identificador del trabajador
     * @param {number} emp_identificacion Identificador del empleador
     * @return {Observable<any>}
     * @memberof ConfiguracionService
     */
    updateTrabajador(payload, tra_identificacion: number, emp_identificacion: number): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}configuracion/nomina-electronica/trabajadores/${emp_identificacion}/${tra_identificacion}`,
            payload,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla de Trabajadores.
     *
     * @param {string} params Parámetros para realizar la descarga
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    descargarExcelGetTrabajadores(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}configuracion/nomina-electronica/lista-trabajadores`,
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
     * Realiza una búsqueda predictiva de trabajadores dada su descripción o nit desde un NgSelect.
     * 
     * @param {string} valor Valor a buscar
     * @param {number} emp_id Id del empleador
     * @return  {Observable<Trabajador[]>}
     * @memberof ConfiguracionService
     */
    searchTrabajadoresNgSelect(valor: string, emp_id: number): Observable<Trabajador[]> {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/nomina-electronica/trabajadores/search-trabajadores?buscar=${valor}&emp_id=${emp_id}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: TrabajadorInteface) => rsp.data));
    }

    /**
     * Obtiene las condiciones asociadas al oferente.
     *
     * @param {string} valor valor a buscar
     * @param {*} aplica Indica el tipo de documento que aplica
     * @param {string} ofe_identificacion Identificación del Oferente
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    obtenerCondiciones(valor: string, aplica: any, ofe_identificacion: string): Observable<any> {
        let aplicaPara = aplica.join('-');

        return this.authHttp.get(
            `${this.apiUrl}configuracion/administracion-recepcion-erp/buscar-ng-select/${valor}/${aplicaPara}/${ofe_identificacion}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Consulta las resoluciones con días o consecutivos próximos a vencer para el usuario autenticado.
     *
     * @return {*} 
     * @memberof ConfiguracionService
     */
    consultaVencimientoResoluciones() {
        return this.authHttp.get(
            `${this.apiUrl}configuracion/resoluciones-vencidas`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Realiza una búsqueda predictiva en la tabla grupos de trabajo usuarios, para obtener los usuarios de tipo validador.
     *
     * @param {string} valor Texto de búsqueda
     * @return {*}  {Observable<any>}
     * @memberof ConfiguracionService
     */
    searchUsuariosGestorValidador(tipo: string, valor: string): Observable<any> {
        let values = {
            tipo: tipo,
            buscar: valor
        }

        return this.authHttp.post(
            `${this.apiUrl}configuracion/grupos-trabajo-usuarios/search-usuarios-gestor-validador`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        ).pipe(map((rsp: UsuarioInteface) => rsp.data));
    }
}
