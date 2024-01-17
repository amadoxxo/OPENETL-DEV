import {Component} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../services/auth/auth.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';

import {RolesService} from '../../../../services/sistema/roles.service';

@Component({
    selector: 'listar-roles',
    templateUrl: './roles.component.html',
    styleUrls: ['./roles.component.scss']
})

export class RolesComponent extends BaseComponentList {
    public selectedValue: any;
    public estadoActual: any;
    public loadingIndicator: any;
    public aclsUsuario: any;

    public trackingInterface: TrackingInterface;

    public registros: any [] = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Código', prop: 'rol_codigo', sorteable: true, width: 100},
        {name: 'Descripción', prop: 'rol_descripcion', sorteable: true},
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
     * @param _rolesService
     */
    constructor(private _router: Router,
                public _auth: Auth,
                private _rolesService: RolesService) {
        super();
        this.trackingInterface = this;
        this.rows = [];
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda de roles
     */
    private init() {
        this.rows = [];
        this.initDataSort('rol_id');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadRoles();
    }

    /**
     * Sobreescribe los parametros de busqueda inline - (Get)
     */
    getSearchParametersInline(): string {
        return 'start=' + this.start + '&' +
            'length=' + this.length + '&' +
            'buscar=' + this.buscar + '&' +
            'columnaOrden=' + this.columnaOrden + '&' +
            'ordenDireccion=' + this.ordenDireccion;
    }

    /**
     * Se encarga de traer la data de los diferentes roles existentes.
     * 
     */
    public loadRoles(): void {
        this.loading(true);
        this._rolesService.listarRoles(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'rol_id': reg.rol_id,
                            'rol_codigo': reg.rol_codigo,
                            'rol_descripcion': reg.rol_descripcion,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los roles', 'OK', 'btn btn-warning', '/dashboard', this._router);
            });
    }

    /**
     * Metodo utilizado por los checkbox en los listados
     * @param evt
     */
    onCheckboxChangeFn(evt: any) {

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

    recargarLista() {
        this.getData();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     * 
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'rol_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'rol_codigo':
                this.columnaOrden = 'codigo';
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
     * Efectua la carga de datos
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadRoles();
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid
     * @param evt
     */
    paginar(evt) {
        this.length = evt;
        this.getData();
    }

    onChangeSelect() {
    }

    /**
     * Evento de selectall del checkbox primario de la grid
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

     /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     * 
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Rol de Usuario para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                const ids = [];
                this.selected.forEach(reg => {
                    ids.push(reg.rol_id);
                });
    
                swal({
                    html: '¿Está seguro de cambiar el estado de los Roles de Usuarios seleccionados?',
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
                        this._rolesService.cambiarEstado(ids).subscribe(
                            response => {
                                this.loadRoles();
                                this.loading(false);
                                this.showSuccess('<h3>Los Roles de Usuario seleccionados han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
        this._router.navigate(['sistema/roles/ver-rol/'+ item.rol_id + '/' + item.rol_codigo]);
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
        this._router.navigate(['sistema/roles/editar-rol/' + item.rol_id]);
    }

    // Aplica solamente a OFEs pero debe implementarse debido a la interface
    onConfigurarDocumentoElectronico(item: any) {
        
    }
}

