import {Component} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../services/auth/auth.service';
import {SistemaService} from '../../../../services/sistema/sistema.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';
import {TiemposAceptacionTacitaGestionarComponent} from '../gestionar/tiempos_aceptacion_tacita_gestionar.component';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';

@Component({
    selector: 'listar-tiempos-aceptacion-tacita',
    templateUrl: './tiempos_aceptacion_tacita.component.html',
    styleUrls: ['./tiempos_aceptacion_tacita.component.scss']
})

export class TiemposAceptacionTacitaComponent extends BaseComponentList implements TrackingInterface {

    public estadoActual: any;
    public loadingIndicator: any;
    private modalTiempoAceptacionTacita: any;
    public aclsUsuario: any;

    public trackingInterface: TrackingInterface;

    public registros: any [] = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Código', prop: 'tat_codigo', sorteable: true},
        {name: 'Descripción', prop: 'tat_descripcion', sorteable: true},
        {name: 'Segundos', prop: 'tat_segundos', sorteable: true, width: 100},
        {name: 'Por Defecto', prop: 'tat_default', sorteable: true, width: 100},
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
     * @param _sistemaService
     */
    constructor(private _router: Router,
                public _auth: Auth,
                private modal: MatDialog,
                private _sistemaService: SistemaService) {
        super();
        this._sistemaService.setSlug = "tiempos-aceptacion-tacita";
        this.trackingInterface = this;
        this.rows = [];
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda de tiempos de aceptación tácita.
     * 
     */
    private init() {
        this.initDataSort('descripcion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadTiemposAceptacionTacita();
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
     * Se encarga de traer la data de los diferentes tiempos de aceptación tácita.
     * 
     */
    public loadTiemposAceptacionTacita(): void {
        this.loading(true);
        this._sistemaService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'tat_id': reg.tat_id,
                            'tat_codigo': reg.tat_codigo,
                            'tat_descripcion': reg.tat_descripcion,
                            'tat_segundos': reg.tat_segundos,
                            'tat_default': reg.tat_default,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Tiempos de Aceptación Tácita', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
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
            case 'tat_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'tat_codigo':
                this.columnaOrden = 'codigo';
                break;
            case 'tat_segundos':
                this.columnaOrden = 'segundos';
                break;
            case 'tat_default':
                this.columnaOrden = 'default';
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
     * Efectúa la carga de datos.
     * 
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadTiemposAceptacionTacita();
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
     * Apertura una ventana modal para crear o editar un tiempo de aceptación tácita.
     * 
     * @param usuario
     */
    public openModalTiempoAceptacionTacita(action: string, tat_id = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            tat_id: tat_id
        };
        this.modalTiempoAceptacionTacita = this.modal.open(TiemposAceptacionTacitaGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un tiempo de aceptación tácita.
     * 
     */
    public closeModalTiempoAceptacionTacita(): void {
        if (this.modalTiempoAceptacionTacita) {
            this.modalTiempoAceptacionTacita.close();
            this.modalTiempoAceptacionTacita = null;
        }
    }

    /**
     * Elimina el tiempo de aceptación tácita seleccionado.
     * 
     * @param tatId
     */
    eliminarTiempoAceptacionTacita(tatId) {
        let that = this;
        swal({
            html: '<h3>¿Está seguro de querer eliminar el tiempo de aceptación tácita?</h3><br>',
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
                that._sistemaService.delete(tatId).subscribe(
                    response => {
                        that.loading(false);
                        // Se actualiza la información del Datatables
                        setTimeout(() => {
                            that.recargarLista();
                        }, 500);
                        this.showTimerAlert('<strong>Tiempo Aceptación Tácita eliminado!.</strong>', 'success', 'center', 2000);
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
                        this.showError('<h3>Error al procesar la información</h3><br>' + errores, 'error', 'Error al eliminar el tiempo de aceptación tácita', 'Cerrar', 'btn btn-danger');
                    }
                );
            }
        }).catch(swal.noop);
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registros seleccionados en la grid.
     * 
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un registro de Tiempo de Aceptación Tácita para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                const ids = [];
                this.selected.forEach(reg => {
                    ids.push(reg.tat_id);
                });
    
                swal({
                    html: '¿Está seguro de cambiar el estado de los registros de Tiempo de Aceptación Tácita seleccionados?',
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
                        this._sistemaService.cambiarEstado(ids).subscribe(
                            response => {
                                this.loadTiemposAceptacionTacita();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros seleccionados de Tiempos de Aceptación Tácita han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
        this.openModalTiempoAceptacionTacita('view', item.tat_id);
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
        this.openModalTiempoAceptacionTacita('edit', item.tat_id);
    }

    // Aplica solamente a OFEs pero debe implementarse debido a la interface
    onConfigurarDocumentoElectronico(item: any) {
        
    }
}

