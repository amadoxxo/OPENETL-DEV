import {Component} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../services/auth/auth.service';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {CentrosOperacionGestionarComponent} from '../gestionar/centros-operacion-gestionar.component';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';
import {ConfiguracionService} from './../../../../services/proyectos-especiales/recepcion/emssanar/configuracion.service';

@Component({
    selector: 'centros-operacion',
    templateUrl: './centros-operacion.component.html',
    styleUrls: ['./centros-operacion.component.scss']
})

export class CentrosOperacionComponent extends BaseComponentList implements TrackingInterface {
    
    public estadoActual: any;
    public loadingIndicator: any;
    private modalCentroOperacion: any;
    public aclsUsuario: any;

    public trackingInterface: TrackingInterface;

    public centrosOperacion: any [] = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Descripción', prop: 'cop_descripcion',    sorteable: true, width: 200},
        {name: 'Creado',      prop: 'fecha_creacion',     sorteable: true, width: 150},
        {name: 'Modificado',  prop: 'fecha_modificacion', sorteable: true, width: 150},
        {name: 'Estado',      prop: 'estado',             sorteable: true, width: 100}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    /**
     * Crea una instancia de CentrosOperacionComponent.
     * @param {Router} _router
     * @param {Auth} _auth
     * @param {MatDialog} modal
     * @param {ConfiguracionService} _configuracionService
     * @memberof CentrosOperacionComponent
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
        this._configuracionService.setSlug = 'centros-operacion';
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @private
     * @memberof CentrosOperacionComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadCentrosOperacion();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [tracking=true]
     * @return {*}  {string}
     * @memberof CentrosOperacionComponent
     */
    public getSearchParametersInline(tracking = true): string {
        let query = 'start=' + this.start + '&' +
        'length=' + this.length + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion;
        if (tracking)
            query += '&tracking=true';

        return query;
    }

    /**
     * Se encarga de traer la data de los diferentes centros operacion.
     *
     * @memberof CentrosOperacionComponent
     */
    public loadCentrosOperacion(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.centrosOperacion.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.centrosOperacion.push(
                        {
                            'cop_id': reg.cop_id,
                            'cop_descripcion': reg.cop_descripcion,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar las Centros Operación', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {any} $evt
     * @memberof CentrosOperacionComponent
     */
    public onPage($evt: any): void {
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.selected = [];
        this.getData();
    }

    /**
     * Método utilizado por los checkbox en los listados.
     *
     * @param {any} evt
     * @memberof CentrosOperacionComponent
     */
    public onCheckboxChangeFn(evt: any): void {

    }

    /**
     * Efectua la carga de datos.
     *
     * @memberof CentrosOperacionComponent
     */
    public getData(): void {
        this.loadingIndicator = true;
        this.loadCentrosOperacion();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     * 
     * @param selected
     */
    public onSelect({selected}): void {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Regarga la lista de datos.
     *
     * @memberof CentrosOperacionComponent
     */
    public recargarLista(): void {
        this.getData();
    }

    /**
     * Apertura una ventana modal para crear o editar un Centro Operacion.
     *
     * @param {string} action
     * @param {any} [cop_id=null]
     * @param {any} [fecha_creacion=null]
     * @param {any} [fecha_modificacion=null]
     * @memberof CentrosOperacionComponent
     */
    public openModalCentrosOperacion(action: string, cop_id: any = null, fecha_creacion: any = null, fecha_modificacion: any = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            cop_id: cop_id,
            fecha_creacion: fecha_creacion,
            fecha_modificacion: fecha_modificacion
        };
        this.modalCentroOperacion = this.modal.open(CentrosOperacionGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un Centro Operacion.
     *
     * @memberof CentrosOperacionComponent
     */
    public closeModalCausalDevolucion(): void {
        if (this.modalCentroOperacion) {
            this.modalCentroOperacion.close();
            this.modalCentroOperacion = null;
        }
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de las centros operacion seleccionados en la grid.
     *
     * @param {string} accion
     * @memberof CentrosOperacionComponent
     */
    public accionesEnBloque(accion: string): void {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Centro Operación para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                const arrCentrosOp:any[] = [];
                this.selected.forEach((reg: any) => {
                    arrCentrosOp.push(reg.cop_id)
                });
                swal({
                    html: '¿Está seguro de cambiar el estado de las Centros Operación seleccionados?',
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
                        this._configuracionService.cambiarEstado({cop_ids: arrCentrosOp.join()}).subscribe(
                            response => {
                                this.loadCentrosOperacion();
                                this.loading(false);
                                this.showSuccess('<h3>Las Centros Operación han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof CentrosOperacionComponent
     */
    public onSearchInline(buscar: string): void {
        this.start = 0;
        this.buscar = buscar;
        this.recargarLista();
    }

    /**
     * Cambia la cantidad de centros operacion del paginado y recarga el listado.
     *
     * @param {number} size
     * @memberof CentrosOperacionComponent
     */
    public onChangeSizePage(size: number): void {
        this.length = size;
        this.recargarLista();
    }

    /**
     * Realiza el ordenamiento de los centros operacion y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los centros operacion
     * @param {string} $order Dirección del orden de los centros operacion [ASC - DESC]
     * @memberof CentrosOperacionComponent
     */
    public onOrderBy(column: string, $order: string): void {
        this.selected = [];
        switch (column) {
            case 'cop_descripcion':
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
     * @memberof CentrosOperacionComponent
     */
    public onOptionMultipleSelected(opcion: any, selected: any[]): void {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un Centro Operacion.
     *
     * @param {any} item
     * @memberof CentrosOperacionComponent
     */
    public onViewItem(item: any): void {
        this.openModalCentrosOperacion('view', item.cop_id, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    /**
     * Gestiona la acción del botón de editar un Centro Operacion
     *
     * @param {any} item
     * @memberof CentrosOperacionComponent
     */
    public onEditItem(item: any): void {
        this.openModalCentrosOperacion('edit', item.cop_id, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    /**
     * Gestiona la acción del botón de eliminar un Centro Operacion.
     *
     * @param {any} item
     * @memberof CentrosOperacionComponent
     */
    public onRequestDeleteItem(item: any): void {
        
    }
}

