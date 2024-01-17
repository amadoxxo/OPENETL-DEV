import {Component} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../services/auth/auth.service';
import {FestivosGestionarComponent} from '../gestionar/festivos_gestionar.component';
import {SistemaService} from '../../../../services/sistema/sistema.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';

@Component({
    selector: 'listar-festivos',
    templateUrl: './festivos.component.html',
    styleUrls: ['./festivos.component.scss']
})

export class FestivosComponent extends BaseComponentList implements TrackingInterface {

    public estadoActual: any;
    public loadingIndicator: any;
    private modalFestivo: any;
    public aclsUsuario: any;

    public trackingInterface: TrackingInterface;

    public registros: any [] = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Descripción', prop: 'fes_descripcion', sorteable: true},
        {name: 'Fecha', prop: 'fes_fecha', sorteable: true},
        {name: 'Estado', prop: 'estado', sorteable: true}
    ];

    public accionesBloque = [
        // {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };
    /**
     * Constructor
     * @param _router
     * @param _auth
     * @param modal
     * @param _SistemaService
     */
    constructor(private _router: Router,
                public _auth: Auth,
                private modal: MatDialog,
                private _sistemaService: SistemaService) {
        super();
        this._sistemaService.setSlug = "festivos";
        this.trackingInterface = this;
        this.rows = [];
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda de festivos.
     * 
     */
    private init() {
        this.initDataSort('fecha');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadFestivos();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     * 
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
     * Se encarga de traer la data de los diferentes festivos existentes.
     * 
     */
    public loadFestivos(): void {
        this.loading(true);
        this._sistemaService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'fes_id': reg.fes_id,
                            'fes_fecha': reg.fes_fecha,
                            'fes_descripcion': reg.fes_descripcion,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Festivos', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt
     */
    public onPage($evt) {
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.selected = [];
        this.getData();
    }

     /**
     * Metodo utilizado por los checkbox en los listados.
     * 
     * @param evt
     */
    onCheckboxChangeFn(evt: any) {

    }

    
    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     * 
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'fes_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'fes_fecha':
                this.columnaOrden = 'fecha';
                break;
            case 'estado':
                this.columnaOrden = 'estado';
                break;
            default:
                break;
        }
        this.start = 0;
        this.ordenDireccion = $order;
        this.recargarLista();
    }

    /**
     * Efectua la carga de datos.
     * 
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadFestivos();
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
     * 
     * @param evt
     */
    paginar(evt) {
        this.length = evt;
        this.getData();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     * 
     * @param selected
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     * 
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.recargarLista();
    }

     /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     * 
     */
    onChangeSizePage(size: number) {
        this.length = size;
        this.recargarLista();
    }

    recargarLista() {
        this.getData();
    }

    /**
     * Apertura una ventana modal para crear o editar un festivo.
     * 
     * @param usuario
     */
    public openModalFestivo(action: string, fes_id = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            fes_id: fes_id
        };
        this.modalFestivo = this.modal.open(FestivosGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un festivo.
     * 
     */
    public closeModalFestivo(): void {
        if (this.modalFestivo) {
            this.modalFestivo.close();
            this.modalFestivo = null;
        }
    }

    /**
     * Elimina el festivo seleccionado.
     * 
     * @param festivoId
     */
    eliminarFestivo(festivoId) {
        let that = this;
        swal({
            html: '<h3>¿Está seguro de querer eliminar el festivo?</h3><br>',
            type: 'error',
            showConfirmButton: true,
            showCancelButton: true,
            confirmButtonClass: 'btn btn-success',
            confirmButtonText: 'Eliminar',
            cancelButtonClass: 'btn btn-danger',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false
        }).then((result) => {
            if (result.value) {
                that.loading(true);
                that._sistemaService.delete(festivoId).subscribe(
                    response => {
                        that.loading(false);
                        // Se actualiza la información del Datatables
                        setTimeout(() => {
                            that.recargarLista();
                        }, 500);
                        this.showTimerAlert('<strong>Festivo eliminado!.</strong>', 'success', 'center', 2000);
                    },
                    error => {
                        that.loading(false);
                        let errores = '';
                        if (error.errors instanceof Array && error.errors.length > 0) {
                            error.errors.forEach(strError => {
                                errores += strError + '<br>';
                            });
                        } else {
                            errores = error.errors;
                        }
                        this.showError('<h3>Error al procesar la información</h3><br>' + errores, 'error', 'Error al eliminar el festivo', 'Cerrar', 'btn btn-danger');
                    }
                );
            }
        }).catch(swal.noop);
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     * 
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Festivo para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let ids = [];
                this.selected.forEach(reg => {
                    let ObjCodigos = new Object;
                    ObjCodigos['fes_id'] = reg.fes_id;
                    ids.push(ObjCodigos);
                });
    
                swal({
                    html: '¿Está seguro de cambiar el estado de los Festivos seleccionados?',
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
                        this._sistemaService.cambiarEstadoCodigos(ids).subscribe(
                            response => {
                                this.loadFestivos();
                                this.loading(false);
                                this.showSuccess('<h3>Los Festivos han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     * 
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro
     * 
     */
    onViewItem(item: any) {
        this.openModalFestivo('view', item.fes_id);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro
     * 
     */
    onRequestDeleteItem(item: any) {
        
    }

    /**
     * Gestiona la acción del botón de editar un registro
     * 
     */
    onEditItem(item: any) {
        this.openModalFestivo('edit', item.fes_id);
    }
        
    // Aplica solamente a OFEs pero debe implementarse debido a la interface
    onConfigurarDocumentoElectronico(item: any) {
        
    }
}

