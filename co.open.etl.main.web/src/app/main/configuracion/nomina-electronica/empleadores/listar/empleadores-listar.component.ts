import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from '../../../../core/base_component_list';
import {Auth} from '../../../../../services/auth/auth.service';
import {ConfiguracionService} from '../../../../../services/configuracion/configuracion.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'app-empleadores-listar',
    templateUrl: './empleadores-listar.component.html',
    styleUrls: ['./empleadores-listar.component.scss']
})
export class EmpleadoresListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    public trackingInterface: TrackingInterface;
    public loadingIndicator: any;
    public registros: any [] = [];
    public aclsUsuario: any;

    public columns: TrackingColumnInterface[] = [
        {name: 'Identificación',                prop: 'emp_identificacion',  sorteable: true, width: 120},
        {name: 'Razón Social o Nombres',        prop: 'razon_nombres',       sorteable: true, width: 400},
        {name: 'Estado',                        prop: 'estado',              sorteable: true, width: 120}
    ];

    public accionesBloque = [
        // {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true,
        cambiarEstadoButton: true
    };

    constructor(
        private _router:Router,
        public _auth: Auth,
        private _configuracionService: ConfiguracionService) {
        super();
        this._configuracionService.setSlug = "nomina-electronica/empleadores";
        this.trackingInterface = this;
        this.rows = [];
    }

    ngOnInit() {
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @private
     * @memberof EmpleadoresListarComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadEmpleador();
    }

    /**
     * Sobrescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Identifica si se debe generar el Excel
     * @return {*}  {string}
     * @memberof EmpleadoresListarComponent
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
     * Permite ir a la pantalla para crear un nuevo empleador.
     *
     * @memberof EmpleadoresListarComponent
     */
    nuevoEmpleador() {
        this._router.navigate(['configuracion/nomina-electronica/empleadores/nuevo-empleador']);
    }

    /**
     * Se encarga de traer la data de los registros de empleadores.
     *
     * @memberof EmpleadoresListarComponent
     */
    public loadEmpleador(): void {
        this.loading(true);
        this._configuracionService.listarEmpleadores(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'emp_id': reg.emp_id,
                            'emp_identificacion': reg.emp_identificacion,
                            'razon_nombres': reg.nombre_completo,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los empleadores', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            }
        );
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {*} $evt Acción del evento
     * @memberof EmpleadoresListarComponent
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
     * @param {*} evt Acción del evento
     * @memberof EmpleadoresListarComponent
     */
    onCheckboxChangeFn(evt: any) {}

    /**
     * Efectúa la carga de datos.
     *
     * @memberof EmpleadoresListarComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadEmpleador();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {*} {selected} Registros seleccionados de la grid
     * @memberof EmpleadoresListarComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Permite recargar la lista del tracking.
     *
     * @memberof EmpleadoresListarComponent
     */
    recargarLista() {
        this.getData();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {*} accion Acción seleccionada
     * @memberof EmpleadoresListarComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Empleador para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let empleadores = '';
                this.selected.forEach(reg => {
                    empleadores += reg.emp_identificacion + ',';
                });
                empleadores = empleadores.slice(0, -1);
                swal({
                    html: '¿Está seguro de cambiar el estado de los Empleadores seleccionados?',
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
                        let payload = {
                            'empleadores': empleadores
                        }
                        this._configuracionService.cambiarEstado(payload).subscribe(
                            response => {
                                this.loadEmpleador();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros de los Empleadores seleccionados han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof EmpleadoresListarComponent
     */
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGetEmpleadores(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de registros de Empleadores', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Permite ir a la pantalla para subir los empleadores.
     *
     * @memberof EmpleadoresListarComponent
     */
    subirEmpleadores() {
        this._router.navigate(['configuracion/nomina-electronica/empleadores/subir-empleadores']);
    }

    /**
     * ecarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar Valor a buscar
     * @memberof EmpleadoresListarComponent
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.recargarLista();
    }

    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     *
     * @param {number} size Cantidad de registros por página
     * @memberof EmpleadoresListarComponent
     */
    onChangeSizePage(size: number) {
        this.length = size;
        this.recargarLista();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     * @param {string} column Columna a ordenar
     * @param {string} $order Dirección del ordenamiento
     * @memberof EmpleadoresListarComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'emp_identificacion':
                this.columnaOrden = 'identificacion';
                break;
            case 'razon_nombres':
                this.columnaOrden = 'razon_nombres';
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
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     *
     * @param {*} opcion Opcion del select
     * @param {any[]} selected Registros seleccionados
     * @memberof EmpleadoresListarComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof EmpleadoresListarComponent
     */
    onViewItem(item: any) {
        this._router.navigate(['configuracion/nomina-electronica/empleadores/ver-empleador/' + item.emp_identificacion  + '/' + item.emp_id]);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof EmpleadoresListarComponent
     */
    onRequestDeleteItem(item: any) { }

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof EmpleadoresListarComponent
     */
    onEditItem(item: any) {
        this._router.navigate(['configuracion/nomina-electronica/empleadores/editar-empleador/' + item.emp_identificacion]);
    }

    /**
     * Gestiona la acción del botón de cambiar estado de un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof EmpleadoresListarComponent
     */
    onCambiarEstadoItem(item){
        this.selected.push(item);
        this.accionesEnBloque('cambiarEstado');
    }

    /**
     * Aplica solamente a OFEs pero debe implementarse debido a la interface.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof EmpleadoresListarComponent
     */
    onRgEstandarItem(item: any) { }
}
