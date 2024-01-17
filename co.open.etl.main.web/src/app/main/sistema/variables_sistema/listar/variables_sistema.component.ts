import swal from 'sweetalert2';
import {Router} from '@angular/router';
import {Component} from '@angular/core';
import {Auth} from '../../../../services/auth/auth.service';
import {BaseComponentList} from '../../../core/base_component_list';
import {VariablesSistemaGestionarComponent} from '../gestionar/variables_sistema_gestionar.component';
import {SistemaService} from '../../../../services/sistema/sistema.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';

@Component({
    selector: 'variables-sistema',
    templateUrl: './variables_sistema.component.html',
    styleUrls: ['./variables_sistema.component.scss']
})

export class VariablesSistemaComponent extends BaseComponentList implements TrackingInterface {

    public  loadingIndicator    : any;
    private modalVariableSistema: any;
    public  aclsUsuario         : any;

    public trackingInterface: TrackingInterface;

    public registros = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Nombre', prop: 'vsi_nombre', sorteable: true},
        {name: 'Valor',  prop: 'vsi_valor',  sorteable: true},
        {name: 'Estado', prop: 'estado',     sorteable: true}
    ];

    public accionesBloque = [];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    /**
     * Crea una instancia de VariablesSistemaComponent.
     * 
     * @param {Router} _router
     * @param {Auth} _auth
     * @param {MatDialog} modal
     * @param {SistemaService} _sistemaService
     * @memberof VariablesSistemaComponent
     */
    constructor(private _router: Router,
                public _auth: Auth,
                private modal: MatDialog,
                private _sistemaService: SistemaService) {
        super();
        this._sistemaService.setSlug = "variables-sistema";
        this.trackingInterface = this;
        this.rows = [];
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda de las variables del sistema.
     * 
     * @memberof VariablesSistemaComponent
     */
    private init() {
        this.initDataSort('vsi_id');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadVariableSistema();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false]
     * @return {*}  {string}
     * @memberof VariablesSistemaComponent
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
     * Se encarga de traer los registros de las variables del sistema.
     * 
     * @memberof VariablesSistemaComponent
     */
    public loadVariableSistema(): void {
        this.loading(true);
        this._sistemaService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'vsi_id'    : reg.vsi_id,
                            'vsi_nombre': reg.vsi_nombre,
                            'vsi_valor' : reg.vsi_valor,
                            'estado'    : reg.estado
                        }
                    );
                });
                this.totalElements    = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow        = this.length !== -1 ? this.length : this.totalElements;
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar las variables del sistema', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt
     * @memberof VariablesSistemaComponent
     */
    public onPage($evt) {
        this.selected = [];
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.getData();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado
     * 
     * @param $evt
     * @memberof VariablesSistemaComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'vsi_nombre':
                this.columnaOrden = 'nombre';
                break;
            case 'vsi_valor':
                this.columnaOrden = 'valor';
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
     * @memberof VariablesSistemaComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadVariableSistema();
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
     * 
     * @param evt
     * @memberof VariablesSistemaComponent
     */
    paginar(evt) {
        this.length = evt;
        this.getData();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     * 
     * @param selected
     * @memberof VariablesSistemaComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     * 
     * @memberof VariablesSistemaComponent
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.getData();
    }

    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     * 
     */
    onChangeSizePage(size: number) {
        this.length = size;
        this.getData();
    }

    /**
     * Apertura una ventana modal para crear o editar una variable del sistema.
     * 
     * @param usuario
     * @memberof VariablesSistemaComponent
     */
    public openModalVariableSistema(action: string, vsi_id = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            vsi_id: vsi_id
        };
        this.modalVariableSistema = this.modal.open(VariablesSistemaGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de una variable del sistema.
     * 
     * @memberof VariablesSistemaComponent
     */
    public closeModalVariableSistema(): void {
        if (this.modalVariableSistema) {
            this.modalVariableSistema.close();
            this.modalVariableSistema = null;
        }
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registros seleccionados en la grid.
     * 
     * @memberof VariablesSistemaComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos una Variable del Sistema para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                const ids = [];
                this.selected.forEach(reg => {
                    ids.push(reg.vsi_id);
                });
                const values = { 'vsi_ids' : ids.join(',')};
                swal({
                    html: '¿Está seguro de cambiar el estado de las variables del sistema seleccionadas?',
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
                        this._sistemaService.cambiarEstado(values).subscribe(
                            response => {
                                this.loadVariableSistema();
                                this.loading(false);
                                this.showSuccess('<h3>Las variables del sistema han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof VariablesSistemaComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro
     * 
     * @memberof VariablesSistemaComponent
     */
    onViewItem(item: any) {
        this.openModalVariableSistema('view', item.vsi_id);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro, Este metodo es requerido para trackingInterface.
     * 
     * @memberof VariablesSistemaComponent
     */
    onRequestDeleteItem(item: any) {
        //
    }

    /**
     * Gestiona la acción del botón de editar un registro
     * 
     * @memberof VariablesSistemaComponent
     */
    onEditItem(item: any) {
        this.openModalVariableSistema('edit', item.vsi_id);
    }

    /**
     * Aplica solamente a OFEs pero debe implementarse debido a la interface.
     *
     * @param {*} item
     * @memberof VariablesSistemaComponent
     */
    onConfigurarDocumentoElectronico(item: any) {
        //
    }
}

