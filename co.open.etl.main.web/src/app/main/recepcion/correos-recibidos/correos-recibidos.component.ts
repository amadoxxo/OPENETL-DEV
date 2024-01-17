import {Component, OnInit, ViewChild} from '@angular/core';
import {BaseComponentList} from 'app/main/core/base_component_list';
import {AbstractControl, FormGroup, FormBuilder} from '@angular/forms';
import {Auth} from '../../../services/auth/auth.service';
import {Router} from '@angular/router';
import {DocumentosTrackingComponent} from '../../commons/documentos-tracking/documentos-tracking.component';
import {CorreosRecibidosService} from '../../../services/recepcion/correos_recibidos.service';
import {DocumentosTrackingColumnInterface, DocumentosTrackingInterface} from '../../commons/documentos-tracking/documentos-tracking-interface';
import {CommonsService} from '../../../services/commons/commons.service';
import {JwtHelperService} from '@auth0/angular-jwt';
import * as moment from 'moment';

@Component({
    selector: 'app-correos-recibidos',
    templateUrl: './correos-recibidos.component.html',
    styleUrls: ['./correos-recibidos.component.scss']
})
export class CorreosRecibidosComponent extends BaseComponentList implements OnInit, DocumentosTrackingInterface {

    @ViewChild('documentosTracking', {static: true}) documentosTracking: DocumentosTrackingComponent;

    public arrProcesado: Array<Object> = [
        {id: 'SI', name: 'SI'},
        {id: 'NO', name: 'NO'},
        {id: 'DOCUMENTO', name: 'DOCUMENTO'}
    ];

    public procesoSelected  : string;
    public ofeIdentificacion: string;
    public existeConsulta   : boolean = false;
    public registros        : any[] = [];
    public ofes             : Array<any>  = [];
    public ofeFNC           : string[];

    public form             : FormGroup;
    public ofe_id           : AbstractControl;
    public pro_id           : AbstractControl;
    public fecha_desde      : AbstractControl;
    public fecha_hasta      : AbstractControl;
    public procesado        : AbstractControl;
    //Modals
    public trackingInterface: DocumentosTrackingInterface;

    public columns: DocumentosTrackingColumnInterface[] = [
        {name: 'Fecha', prop: 'epm_fecha_correo', sorteable: true, width: '100'},
        {name: 'Emisor', prop: 'emisor', sorteable: true, width: '100'},
        {name: 'Subject', prop: 'epm_subject', sorteable: true, width: '150'},
        {name: 'Estado Procesamiento', prop: 'epm_procesado', sorteable: true, width: '250'}
    ];

    /**
     * Constructor de CorreosRecibidosComponent.
     * 
     * @param {Auth} _auth
     * @param {FormBuilder} _fb
     * @param {Router} _router
     * @param {CommonsService} _commonsService
     * @param {JwtHelperService} _jwtHelperService
     * @param {CorreosRecibidosService} _correosRecibidosService
     * @memberof CorreosRecibidosComponent
     */
    constructor(
        public _auth: Auth,
        private _fb: FormBuilder,
        private _router: Router,
        private _commonsService: CommonsService,
        private _jwtHelperService: JwtHelperService,
        private _correosRecibidosService: CorreosRecibidosService
    ) {
        super();
        this.rows              = [];
        this.trackingInterface = this;
        this.procesoSelected   = 'NO';

        let usuario   = this._jwtHelperService.decodeToken();
        if(usuario && usuario.ofe_recepcion_fnc && usuario.ofe_recepcion_fnc === 'SI' && usuario.oferentes)
            this.ofeFNC = usuario.oferentes;

        this.init();
    }

    /**
     * Inicializa las variables y crea el formulario de filtros.
     *
     * @private
     * @memberof CorreosRecibidosComponent
     */
    private init() {
        this.initDataSort('cdo_fecha');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.form = this._fb.group({
            ofe_id      : this.requerido(),
            pro_id      : [''],
            fecha_desde : this.requerido(),
            fecha_hasta : this.requerido(),
            procesado   : this.requerido(),
        });

        this.ofe_id      = this.form.controls['ofe_id'];
        this.pro_id      = this.form.controls['pro_id'];
        this.fecha_desde = this.form.controls['fecha_desde'];
        this.fecha_hasta = this.form.controls['fecha_hasta'];
        this.procesado   = this.form.controls['procesado'];
    }

    /**
     * Crea un JSON con los parámetros de búsqueda.
     *
     * @return {*} 
     * @memberof CorreosRecibidosComponent
     */
    public getSearchParametersObject() {

        const fecha_desde = this.fecha_desde && this.fecha_desde.value !== '' ? String(moment(this.fecha_desde.value).format('YYYY-MM-DD')) : '';
        const fecha_hasta = this.fecha_hasta && this.fecha_hasta.value !== '' ? String(moment(this.fecha_hasta.value).format('YYYY-MM-DD')) : '';

        let parametros = {
            start              :  this.start,
            length             :  this.length,
            columnaOrden       :  this.columnaOrden,
            ordenDireccion     :  this.ordenDireccion,
            fecha_desde        :  fecha_desde,
            fecha_hasta        :  fecha_hasta,
            ofe_identificacion :  this.ofeIdentificacion,
            pro_id             :  this.pro_id.value,
            procesado          :  this.procesoSelected,
            buscar             :  String((this.documentosTracking.buscar) ? this.documentosTracking.buscar : ''),
        };

        if (this.pro_id && this.pro_id.value)
            parametros.pro_id = this.pro_id.value;
        else
            delete parametros.pro_id;

        return parametros;
    }

    /**
     * Se encarga de traer la data de los correos recibidos.
     *
     * @memberof CorreosRecibidosComponent
     */
    public loadCorreosRecibidos(): void {
        this.loading(true);
        const parameters = this.getSearchParametersObject();
        this._correosRecibidosService.listar(parameters).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'epm_id'          : reg.epm_id,
                            'epm_fecha_correo': reg.epm_fecha_correo,
                            'emisor'          : reg.ofe_identificacion,
                            'epm_subject'     : reg.epm_subject,
                            'epm_procesado'   : reg.epm_procesado,
                        }
                    );
                });
                this.totalElements = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow = this.length !== -1 ? this.length : this.totalElements;
                if (this.rows.length > 0) {
                    this.existeConsulta = true;
                } else {
                    this.existeConsulta = false;
                }
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Correos Recibidos', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            }
        );
    }

    /**
     * ngOnInit de CorreosRecibidosComponent.
     *
     * @memberof CorreosRecibidosComponent
     */
    ngOnInit() {
        this.pro_id.disable();
        this.cargarOfes();
    }

    /**
     * Carga los OFEs en el select de receptores.
     *
     * @private
     * @memberof CorreosRecibidosComponent
     */
    private cargarOfes() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false').subscribe(
            result => {
                this.loading(false);
                this.ofes = [];
                let autorizado = false;
                result.data.ofes.forEach(ofe => {
                    if(ofe.ofe_recepcion === 'SI') {
                        ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                        this.ofes.push(ofe);
                        if(this.ofeFNC.includes(ofe.ofe_identificacion))
                            autorizado = true;
                    }
                });
                if(!autorizado)
                    this.showError('<h4>Permiso Denegado</h4>', 'error', 'Usuario no autorizado', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
                else if(this.ofes.length === 1) {
                    this.ofe_id.setValue(this.ofes[0].ofe_id);
                    this.ofeIdentificacion = this.ofes[0].ofe_identificacion;
                    this.pro_id.enable();
                }
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los OFEs', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Recarga los registros con la información filtrada.
     *
     * @param {*} values Value del formulario
     * @memberof CorreosRecibidosComponent
     */
    searchDocumentos(values): void {
        this.loading(true);
        if (this.form.valid) {
            this.onPage({
                offset: 0
            });
            this.documentosTracking.tracking.offset = 0;
        } else {
            this.loading(false);
        }
    }

    /**
     * Gestiona el evento de paginacion de la grid.
     *
     * @param {*} evt
     * @memberof CorreosRecibidosComponent
     */
    public onPage($evt) {
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.selected = [];
        this.getData();
    }

    /**
     * Método utilizado por los checkbox en los listados.
     *
     * @param {*} evt
     * @memberof CorreosRecibidosComponent
     */
    onCheckboxChangeFn(evt: any) {}

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     * @param {string} column
     * @param {string} $order
     * @memberof CorreosRecibidosComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'emisor':
                this.columnaOrden = 'ofe_identificacion';
                break;
            case 'epm_fecha_correo':
                this.columnaOrden = 'fecha_correo';
                break;
            case 'epm_subject':
                this.columnaOrden = 'subject';
                break;
            case 'epm_procesado':
                this.columnaOrden = 'procesado';
                break;
            default:
                break;
        }
        this.ordenDireccion = $order;
        this.getData();
    }

    /**
     * Efectua la carga de datos.
     *
     * @memberof CorreosRecibidosComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadCorreosRecibidos();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {*} {selected}
     * @memberof CorreosRecibidosComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar
     * @memberof CorreosRecibidosComponent
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = this.documentosTracking.buscar;
        this.getData();
    }

    /**
     * Gestiona la acción de los botones de opciones de un registro.
     *
     * @memberof CorreosRecibidosComponent
     */
    onOptionItem() {}

    /**
     * Gestiona la acción del botón de descarga de documentos
     *
     * @memberof CorreosRecibidosComponent
     */
    onDescargarItems() {}

    /**
     * Gestiona la acción del botón de envío por correo de documentos
     *
     * @memberof CorreosRecibidosComponent
     */
    onEnviarItems() {}

    /**
     * Gestiona la acción del botón de ver un registro
     *
     * @memberof CorreosRecibidosComponent
     */
    onDescargarExcel() {}

    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     *
     * @param {number} size Cantidad de registros a paginar
     * @memberof CorreosRecibidosComponent
     */
    onChangeSizePage(size: number) {
        this.length = size;
        this.getData();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     *
     * @memberof CorreosRecibidosComponent
     */
    onOptionMultipleSelected() {}

    /**
     * Monitoriza cuando el valor del select de OFEs cambia para realizar acciones determinadas de acuerdo al OFE.
     * 
     * @param {object} ofe Objeto con la información del OFE seleccionado
     * @memberof CorreosRecibidosComponent
     */
    ofeHasChanged(ofe) {
        this.ofeIdentificacion = ofe.ofe_identificacion;
    }

}