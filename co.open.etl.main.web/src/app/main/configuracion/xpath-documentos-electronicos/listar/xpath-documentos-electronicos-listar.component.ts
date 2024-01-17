import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component} from '@angular/core';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {Auth} from '../../../../services/auth/auth.service';
import {BaseComponentList} from '../../../core/base_component_list';
import {CommonsService} from '../../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {XPathDocumentosElectronicosGestionarComponent} from '../gestionar/xpath-documentos-electronicos-gestionar.component';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from './../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'app-xpath-documentos-electronicos-listar',
    templateUrl: './xpath-documentos-electronicos-listar.component.html',
    styleUrls: ['./xpath-documentos-electronicos-listar.component.scss']
})
export class XPathDocumentosElectronicosListarComponent extends BaseComponentList implements TrackingInterface {

    public loadingIndicator      : any;
    public aclsUsuario           : any;
    public registros             = [];
    public trackingInterface     : TrackingInterface;
    private modalXPathGestionar  : any;
    public accionesBloque        = [];
    public tipoConfiguracion    = '';
    public tituloModulo         = '';
    // Permisos acciones
    public permisoEditar        = '';
    public permisoVer           = '';
    public permisoCambiarEstado = '';

    public columns: TrackingColumnInterface[] = [
        {name: 'Tipo Documento', prop: 'xde_aplica_para',    sorteable: true, width: 100},
        {name: 'Descripción',    prop: 'xde_descripcion',    sorteable: true, width: 200},
        {name: 'Creado',         prop: 'fecha_creacion',     sorteable: true, width: 90},
        {name: 'Modificado',     prop: 'fecha_modificacion', sorteable: true, width: 90},
        {name: 'Estado',         prop: 'estado',             sorteable: true, width: 80}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    /**
     * Crea una instancia de XPathDocumentosElectronicosListarComponent.
     * 
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {MatDialog} _modal
     * @param {ConfiguracionService} _configuracionService
     * @param {CommonsService} _commonsService
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    constructor(
        public _auth: Auth,
        private _router:Router,
        private _modal: MatDialog,
        private _configuracionService: ConfiguracionService,
        private _commonsService: CommonsService
    ) {
        super();
        if(this._router.url.indexOf('xpath-documentos-electronicos/estandar') !== -1) {
            this.tipoConfiguracion    = 'estandar';
            this.tituloModulo         = 'XPath Documentos Electrónicos Estándar';
            this.permisoEditar        = 'ConfiguracionXPathDEEstandarEditar';
            this.permisoVer           = 'ConfiguracionXPathDEEstandarVer';
            this.permisoCambiarEstado = 'ConfiguracionXPathDEEstandarCambiarEstado';
        } else {
            this.tipoConfiguracion    = 'personalizados';
            this.tituloModulo         = 'XPath Documentos Electrónicos Personalizados'
            this.permisoEditar        = 'ConfiguracionXPathDEPersonalizadoEditar';
            this.permisoVer           = 'ConfiguracionXPathDEPersonalizadoVer';
            this.permisoCambiarEstado = 'ConfiguracionXPathDEPersonalizadoCambiarEstado';
        }

        this._configuracionService.setSlug = 'xpath-documentos-' + this.tipoConfiguracion;
        this.trackingInterface = this;
        this.rows = [];
        this.init();
    }

    /**
     * Permite aperturar la modal para crear un xpath documento electrónico.
     *
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    nuevoXPathDocumento() {
        this.openModalXPathDocumento('new');
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     * 
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion   = 'DESC';
        this.aclsUsuario      = this._auth.getAcls();

        if(this.tipoConfiguracion === 'personalizados')
            this.columns.unshift({name: 'OFE / Receptor',prop: 'ofe_razon_social',sorteable: true, width: 180});

        this.loadXPathDocumento();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Aplica retorno en excel
     * @return {string}
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    getSearchParametersInline(excel = false): string {
        let query = 'start=' + this.start + '&' +
        'length=' + this.length + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion;
        if (excel)
            query += '&excel=true';

        return query;
    }

    /**
     * Se encarga de traer la data de los diferentes registros.
     * 
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public loadXPathDocumento(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    if(this.tipoConfiguracion === 'estandar') { 
                        this.registros.push(
                            {
                                'xde_id'             : reg.xde_id,
                                'xde_aplica_para'    : reg.xde_aplica_para,
                                'xde_descripcion'    : reg.xde_descripcion,
                                'fecha_creacion'     : reg.fecha_creacion,
                                'fecha_modificacion' : reg.fecha_modificacion,
                                'estado'             : reg.estado
                            }
                        );
                    } else {
                        this.registros.push(
                            {
                                'xde_id'             : reg.xde_id,
                                'ofe_identificacion' : reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion,
                                'ofe_razon_social'   : reg.get_configuracion_obligado_facturar_electronicamente.ofe_razon_social,
                                'xde_aplica_para'    : reg.xde_aplica_para,
                                'xde_descripcion'    : reg.xde_descripcion,
                                'fecha_creacion'     : reg.fecha_creacion,
                                'fecha_modificacion' : reg.fecha_modificacion,
                                'estado'             : reg.estado
                            }
                        );
                    }
                });
                this.loadingIndicator = false;
                this.totalElements    = res.filtrados;
                this.totalShow        = this.length !== -1 ? this.length : this.totalElements;
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los XPath Documentos Electrónicos', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt Cantidad de registros
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public onPage($evt) {
        this.selected = [];
        this.page     = $evt.offset;
        this.start    = $evt.offset * this.length;
        this.getData();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los registros
     * @param {string} $order Dirección del orden de los registros [ASC - DESC]
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'ofe_razon_social':
                this.columnaOrden = 'oferente';
                break;
            case 'xde_aplica_para':
                this.columnaOrden = 'aplica_para';
                break;
            case 'xde_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'fecha_creacion':
                this.columnaOrden = 'creacion';
                break;
            case 'fecha_modificacion':
                this.columnaOrden = 'modificacion';
                break;
            case 'estado':
                this.columnaOrden = 'estado';
                break;
            default:
                break;
        }
        this.start = 0;
        this.ordenDireccion = $order;
        this.getData();
    }
    /**
     * Efectua la carga de datos.
     * 
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadXPathDocumento();
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
     * 
     * @param evt Cantidad de registros
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public paginar(evt) {
        this.length = evt;
        this.getData();
    }

    /**
     * Evento de select all del checkbox primario de la grid.
     * 
     * @param selected Resgistro seleccionado
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     * 
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.getData();
    }

    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     * 
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public onChangeSizePage(size: number) {
        this.length = size;
        this.getData();
    }

    /**
     * Apertura una ventana modal para crear o editar un xpath documento electrónico.
     *
     * @param {string} action Acción a ejecutar
     * @param {string} [xde_id=null] Id del registro a procesar
     * @param {string} [ofe_identificacion=null]
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public openModalXPathDocumento(action: string, xde_id: string = null, ofe_identificacion: string = null): void {
        this.loading(true);
        let ofes = [];
        let registro;
        this._commonsService.getOfes("recepcion=true&emision=true").subscribe(
            result => {
                ofes = result.data.ofes;
                if(action === 'new') {
                    this.loading(false);
                    const modalConfig = new MatDialogConfig();
                    modalConfig.autoFocus = true;
                    modalConfig.width = '650px';
                    modalConfig.data = {
                        parent            : this,
                        action            : action,
                        tipoConfiguracion : this.tipoConfiguracion,
                        ofes              : ofes
                    };
                    this.modalXPathGestionar = this._modal.open(XPathDocumentosElectronicosGestionarComponent, modalConfig);
                } else {
                    this._configuracionService.get(xde_id).subscribe(
                        res => {
                            if (res) {
                                registro = res.data;
                                this.loading(false);
                                const modalConfig = new MatDialogConfig();
                                modalConfig.autoFocus = true;
                                modalConfig.width = '650px';
                                modalConfig.data = {
                                    parent            : this,
                                    action            : action,
                                    tipoConfiguracion : this.tipoConfiguracion,
                                    xde_id            : xde_id,
                                    ofe_identificacion: ofe_identificacion,
                                    ofes              : ofes,
                                    item              : registro
                                };
                                this.modalXPathGestionar = this._modal.open(XPathDocumentosElectronicosGestionarComponent, modalConfig);
                            }
                        },
                        error => {
                            this.loading(false);
                            this.showError('<h4>' + error.message + '</h4>', 'error', 'Error al cargar el registro', 'Ok', 'btn btn-danger');
                        }
                    );
                }
            }, error => {
                this.loading(false);
                this.showError('<h4>' + error.message + '</h4>', 'error', 'Error al cargar los ofes', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de una variable del sistema.
     * 
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public closeModalXPath(): void {
        if (this.modalXPathGestionar) {
            this.modalXPathGestionar.close();
            this.modalXPathGestionar = null;
        }
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en el tracking.
     *
     * @param {*} accion Acción a procesar
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un registro para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let identificadores = [];
                let params;
                this.selected.forEach(reg => {
                    identificadores.push(reg.xde_id);
                });
                params = {
                    'xpath' : identificadores.join(',')
                };
                swal({
                    html: '¿Está seguro de cambiar el estado de registros seleccionados?',
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonClass: 'btn btn-success',
                    confirmButtonText: 'Aceptar',
                    cancelButtonText: 'Cancelar',
                    cancelButtonClass: 'btn btn-danger',
                    buttonsStyling: false,
                    allowOutsideClick: false
                })
                .then((result) => {
                    if (result.value) {
                        this.loading(true);
                        this._configuracionService.cambiarEstado(params).subscribe(
                            response => {
                                this.loadXPathDocumento();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros seleccionados han cambiado de estado correctamente</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
                            },
                            error => {
                                this.loading(false);
                                const texto_errores = this.parseError(error);
                                this.loadingIndicator = false;
                                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cambiar de estado', 'OK', 'btn btn-danger');
                            }
                        );
                    }
                }).catch(swal.noop);
            }
        } 
        this.selected = [];
    }

    /**
     * Descarga los registros filtrados en un archivos de excel.
     * 
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de los XPath Documentos Electrónicos', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     *
     * @param {*} accion Acción a procesar
     * @param {any[]} selected Registro seleccionado
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public onOptionMultipleSelected(accion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(accion);
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     * 
     * @param {*} item Registro seleccionado
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public onViewItem(item: any) {
        if(this.tipoConfiguracion === 'estandar')
            this.openModalXPathDocumento('view', item.xde_id);
        else
            this.openModalXPathDocumento('view', item.xde_id, item.ofe_identificacion);
    }

    /**
     * Gestiona la acción del botón de editar un registro.
     * 
     * @param {*} item Registro seleccionado
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public onEditItem(item: any) {
        if(this.tipoConfiguracion === 'estandar')
            this.openModalXPathDocumento('edit', item.xde_id);
        else
            this.openModalXPathDocumento('edit', item.xde_id, item.ofe_identificacion);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof XPathDocumentosElectronicosListarComponent
     */
    public onRequestDeleteItem(item: any) {}
}
