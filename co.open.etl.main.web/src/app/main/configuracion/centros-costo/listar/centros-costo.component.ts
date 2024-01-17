import {Component} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../services/auth/auth.service';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {CentrosCostoGestionarComponent} from '../gestionar/centros-costo-gestionar.component';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';
import {ConfiguracionService} from './../../../../services/proyectos-especiales/recepcion/emssanar/configuracion.service';

@Component({
    selector: 'centros-costo',
    templateUrl: './centros-costo.component.html',
    styleUrls: ['./centros-costo.component.scss']
})

export class CentrosCostoComponent extends BaseComponentList implements TrackingInterface {
    
    public estadoActual: any;
    public loadingIndicator: any;
    private modalCentroCosto: any;
    public aclsUsuario: any;

    public trackingInterface: TrackingInterface;

    public centrosCosto: any [] = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Código',      prop: 'cco_codigo',         sorteable: true, width: 100},
        {name: 'Descripción', prop: 'cco_descripcion',    sorteable: true, width: 200},
        {name: 'Creado',      prop: 'fecha_creacion',     sorteable: true, width: 150},
        {name: 'Modificado',  prop: 'fecha_modificacion', sorteable: true, width: 150},
        {name: 'Estado',      prop: 'estado',             sorteable: true, width: 100}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    /**
     * Crea una instancia de CentrosCostoComponent.
     * @param {Router} _router
     * @param {Auth} _auth
     * @param {MatDialog} modal
     * @param {ConfiguracionService} _configuracionService
     * @memberof CentrosCostoComponent
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
        this._configuracionService.setSlug = 'centros-costo';
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @private
     * @memberof CentrosCostoComponent
     */
    private init(): void {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadCentrosCosto();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [tracking=true]
     * @return {string}
     * @memberof CentrosCostoComponent
     */
    public getSearchParametersInline(tracking: boolean = true): string {
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
     * Se encarga de traer la data de los diferentes centros costo.
     *
     * @memberof CentrosCostoComponent
     */
    public loadCentrosCosto(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.centrosCosto.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.centrosCosto.push(
                        {
                            'cco_id': reg.cco_id,
                            'cco_codigo': reg.cco_codigo,
                            'cco_descripcion': reg.cco_descripcion,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Centros Costo de Corrección', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {any} $evt
     * @memberof CentrosCostoComponent
     */
    public onPage($evt: any) {
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.selected = [];
        this.getData();
    }

    /**
     * Método utilizado por los checkbox en los listados.
     * 
     * @param {any} evt
     * @memberof CentrosCostoComponent
     */
    public onCheckboxChangeFn(evt: any): void {

    }

    /**
     * Efectua la carga de datos.
     *
     * @memberof CentrosCostoComponent
     */
    public getData(): void {
        this.loadingIndicator = true;
        this.loadCentrosCosto();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {any} {selected}
     * @memberof CentrosCostoComponent
     */
    public onSelect({selected}: any): void {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }
    /**
     * Recarga la lista de datos.
     *
     * @memberof CentrosCostoComponent
     */
    public recargarLista(): void {
        this.getData();
    }

    /**
     * Apertura una ventana modal para crear o editar un Centro Costo.
     *
     * @param {string} action
     * @param {any} [cco_id=null]
     * @param {any} [cco_codigo=null]
     * @param {any} [fecha_creacion=null]
     * @param {any} [fecha_modificacion=null]
     * @memberof CentrosCostoComponent
     */
    public openModalCentrosCosto(action: string, cco_id: any = null, cco_codigo: any = null, fecha_creacion: any = null, fecha_modificacion = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            cco_id: cco_id,
            cco_codigo: cco_codigo,
            fecha_creacion: fecha_creacion,
            fecha_modificacion: fecha_modificacion
        };
        this.modalCentroCosto = this.modal.open(CentrosCostoGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un Centro Costo.
     *
     * @memberof CentrosCostoComponent
     */
    public closeModalCentroCosto(): void {
        if (this.modalCentroCosto) {
            this.modalCentroCosto.close();
            this.modalCentroCosto = null;
        }
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los centros costo seleccionados en la grid.
     *
     * @param {any} accion
     * @memberof CentrosCostoComponent
     */
    public accionesEnBloque(accion: any): void {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Centro de Costo para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                const arrCosto:any[] = [];
                this.selected.forEach((reg: any)=>{​​​​​
                    arrCosto.push(reg.cco_codigo);
                }​​​​​);
                swal({
                    html: '¿Está seguro de cambiar el estado de los Centros Costo de Corrección seleccionados?',
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
                        this._configuracionService.cambiarEstado({cco_codigos: arrCosto.join()}).subscribe(
                            response => {
                                this.loadCentrosCosto();
                                this.loading(false);
                                this.showSuccess('<h3>Los Centros Costo de Corrección han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof CentrosCostoComponent
     */
    public onSearchInline(buscar: string): void {
        this.start = 0;
        this.buscar = buscar;
        this.recargarLista();
    }

    /**
     * Cambia la cantidad de centros costo del paginado y recarga el listado.
     *
     * @param {number} size
     * @memberof CentrosCostoComponent
     */
    public onChangeSizePage(size: number): void {
        this.length = size;
        this.recargarLista();
    }

    /**
     * Realiza el ordenamiento de los centros costo y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los centros costo
     * @param {string} $order Dirección del orden de los centros costo [ASC - DESC]
     * @memberof CentrosCostoListarComponent
     */
    public onOrderBy(column: string, $order: string): void {
        this.selected = [];
        switch (column) {
            case 'cco_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'cco_codigo':
                this.columnaOrden = 'codigo';
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
     * @memberof CentrosCostoComponent
     */
    public onOptionMultipleSelected(opcion: any, selected: any[]): void {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un Centro Costo.
     *
     * @param {*} item
     * @memberof CentrosCostoComponent
     */
    public onViewItem(item: any): void {
        this.openModalCentrosCosto('view', item.cco_id, item.cco_codigo, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    /**
     * Gestiona la acción del botón de editar un Centro Costo.
     *
     * @param {any} item
     * @memberof CentrosCostoComponent
     */
    public onEditItem(item: any): void {
        this.openModalCentrosCosto('edit', item.cco_id, item.cco_codigo, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    /**
     * Gestiona la acción del botón de eliminar un Centro Costo.
     *
     * @param {*} item
     * @memberof CentrosCostoComponent
     */
    public onRequestDeleteItem(item: any): void {
        
    }
}

