import {Component} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../services/auth/auth.service';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {CausalesDevolucionGestionarComponent} from '../gestionar/causales-devolucion-gestionar.component';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';
import {ConfiguracionService} from '../../../../services/proyectos-especiales/recepcion/emssanar/configuracion.service';

@Component({
    selector: 'causales-devolucion',
    templateUrl: './causales-devolucion.component.html',
    styleUrls: ['./causales-devolucion.component.scss']
})

export class CausalesDevolucionComponent extends BaseComponentList implements TrackingInterface {
    
    public estadoActual: any;
    public loadingIndicator: any;
    private modalCausalDevolucion: any;
    public aclsUsuario: any;

    public trackingInterface: TrackingInterface;

    public causalesDevolucion: any [] = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Descripción', prop: 'cde_descripcion',    sorteable: true, width: 200},
        {name: 'Creado',      prop: 'fecha_creacion',     sorteable: true, width: 150},
        {name: 'Modificado',  prop: 'fecha_modificacion', sorteable: true, width: 150},
        {name: 'Estado',      prop: 'estado',             sorteable: true, width: 100}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    /**
     * Crea una instancia de CausalesDevolucionComponent.
     * @param {Router} _router
     * @param {Auth} _auth
     * @param {MatDialog} modal
     * @param {ConfiguracionService} _configuracionService
     * @memberof CausalesDevolucionComponent
     */
    constructor(
        private _router: Router,
        public _auth: Auth,
        private modal: MatDialog,
        private _configuracionService: ConfiguracionService
    ) {
        super();
        this.trackingInterface = this;
        this.rows = [];
        this._configuracionService.setSlug = 'causales-devolucion';
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @private
     * @memberof CausalesDevolucionComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadCausalDevolucion();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [tracking=true]
     * @return {string}
     * @memberof CausalesDevolucionComponent
     */
    public getSearchParametersInline(tracking:boolean = true): string {
        let query:string = 'start=' + this.start + '&' +
        'length=' + this.length + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion;
        if (tracking)
            query += '&tracking=true';

        return query;
    }

    /**
     * Se encarga de traer la data de los diferentes causales devolucion.
     *
     * @memberof CausalesDevolucionComponent
     */
    public loadCausalDevolucion(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.causalesDevolucion.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.causalesDevolucion.push(
                        {
                            'cde_id': reg.cde_id,
                            'cde_descripcion': reg.cde_descripcion,
                            'fecha_creacion': reg.fecha_creacion,
                            'fecha_modificacion': reg.fecha_modificacion,
                            'estado': reg.estado
                        }
                    );
                });
                this.totalElements = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow = this.length !== -1 ? this.length : this.totalElements;
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar las Causales Devolución', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {any} $evt
     * @memberof CausalesDevolucionComponent
     */
    public onPage($evt:any): void {
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.selected = [];
        this.getData();
    }

    /**
     * Método utilizado por los checkbox en los listados.
     *
     * @param {any} evt
     * @memberof CausalesDevolucionComponent
     */
    public onCheckboxChangeFn(evt: any): void {

    }

    /**
     * Efectua la carga de datos.
     *
     * @memberof CausalesDevolucionComponent
     */
    public getData(): void {
        this.loadingIndicator = true;
        this.loadCausalDevolucion();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {any} {selected}
     * @memberof CausalesDevolucionComponent
     */
    public onSelect({selected}: any): void {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     *  Regarla la lista de datos.
     *
     * @memberof CausalesDevolucionComponent
     */
    public recargarLista(): void {
        this.getData();
    }

    /**
     * Apertura una ventana modal para crear o editar un Causal Devolucion.
     *
     * @param {string} action
     * @param {any} [cde_id=null]
     * @param {any} [fecha_creacion=null]
     * @param {any} [fecha_modificacion=null]
     * @memberof CausalesDevolucionComponent
     */
    public openModalCausalesDevolucion(action: string, cde_id: any = null, fecha_creacion: any = null, fecha_modificacion: any = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            cde_id: cde_id,
            fecha_creacion: fecha_creacion,
            fecha_modificacion: fecha_modificacion
        };
        this.modalCausalDevolucion = this.modal.open(CausalesDevolucionGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un Causal Devolucion.
     *
     * @memberof CausalesDevolucionComponent
     */
    public closeModalCausalDevolucion(): void {
        if (this.modalCausalDevolucion) {
            this.modalCausalDevolucion.close();
            this.modalCausalDevolucion = null;
        }
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de las causales devolucion seleccionados en la grid.
     *
     * @param {string} accion
     * @memberof CausalesDevolucionComponent
     */
    public accionesEnBloque(accion: string): void {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Causal Devolución para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                const arrCausal:any[] = [];
                this.selected.forEach((reg: any) => {
                    arrCausal.push(reg.cde_id)
                });
                swal({
                    html: '¿Está seguro de cambiar el estado de las Causales Devolución seleccionados?',
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
                        this._configuracionService.cambiarEstado({cde_ids: arrCausal.join()}).subscribe(
                            response => {
                                this.loadCausalDevolucion();
                                this.loading(false);
                                this.showSuccess('<h3>Las Causales Devolución han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * Recarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar
     * @memberof CausalesDevolucionComponent
     */
    public onSearchInline(buscar: string): void {
        this.start = 0;
        this.buscar = buscar;
        this.recargarLista();
    }

    /**
     * Cambia la cantidad de causales devolucion del paginado y recarga el listado.
     *
     * @param {number} size
     * @memberof CausalesDevolucionComponent
     */
    public onChangeSizePage(size: number): void {
        this.length = size;
        this.recargarLista();
    }

    /**
     * Realiza el ordenamiento de los causales devolucion y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los causales devolucion
     * @param {string} $order Dirección del orden de los causales devolucion [ASC - DESC]
     * @memberof CausalesDevolucionComponent
     */
    public onOrderBy(column: string, $order: string): void {
        this.selected = [];
        switch (column) {
            case 'cde_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'fecha_creacion':
            case 'fecha_modificacion':
            case 'estado':
                this.columnaOrden = column;
                break;
            default:
                this.columnaOrden = 'fecha_modificacion';
                break;
        }
        this.start = 0;
        this.ordenDireccion = $order;
        this.recargarLista();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     *
     * @param {any} opcion
     * @param {any[]} selected
     * @memberof CausalesDevolucionComponent
     */
    public onOptionMultipleSelected(opcion: any, selected: any[]): void {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un Causal Devolucion.
     *
     * @param {any} item
     * @memberof CausalesDevolucionComponent
     */
    public onViewItem(item: any): void {
        this.openModalCausalesDevolucion('view', item.cde_id, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    /**
     * Gestiona la acción del botón de editar un Causal Devolucion.
     *
     * @param {any} item
     * @memberof CausalesDevolucionComponent
     */
    public onEditItem(item: any): void {
        this.openModalCausalesDevolucion('edit', item.cde_id, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    /**
     * Gestiona la acción del botón de eliminar un Causal Devolucion.
     *
     * @param {any} item
     * @memberof CausalesDevolucionComponent
     */
    public onRequestDeleteItem(item: any): void {
        
    }
}

