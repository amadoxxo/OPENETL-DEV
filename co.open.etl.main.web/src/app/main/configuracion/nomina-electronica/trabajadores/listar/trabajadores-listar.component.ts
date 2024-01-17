import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from '../../../../core/base_component_list';
import {Auth} from '../../../../../services/auth/auth.service';
import {ConfiguracionService} from '../../../../../services/configuracion/configuracion.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'app-trabajadores-listar',
    templateUrl: './trabajadores-listar.component.html',
    styleUrls: ['./trabajadores-listar.component.scss']
})
export class TrabajadoresListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    public trackingInterface: TrackingInterface;
    public loadingIndicator: any;
    public registros: any [] = [];
    public aclsUsuario: any;

    public columns: TrackingColumnInterface[] = [
        {name: 'Empleador',              prop: 'nombre_empleador',    sorteable: true, width: 120},
        {name: 'Identificación',         prop: 'tra_identificacion',  sorteable: true, width: 120},
        {name: 'Nombres y Apellidos',    prop: 'tra_nombre_completo', sorteable: true, width: 280},
        {name: 'Estado',                 prop: 'estado',              sorteable: true, width: 120}
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
        this._configuracionService.setSlug = "nomina-electronica/trabajadores";
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
     * @memberof TrabajadoresListarComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadTrabajadores();
    }

    /**
     * Sobrescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Identifica si se debe generar el Excel
     * @return {*}  {string}
     * @memberof TrabajadoresListarComponent
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
     * Permite ir a la pantalla para crear un nuevo trabajador.
     *
     * @memberof TrabajadoresListarComponent
     */
    nuevoTrabajador() {
        this._router.navigate(['configuracion/nomina-electronica/trabajadores/nuevo-trabajador']);
    }

    /**
     * Se encarga de traer la data de los registros de trabajadores.
     *
     * @memberof TrabajadoresListarComponent
     */
    public loadTrabajadores(): void {
        this.loading(true);
        this._configuracionService.listarTrabajadores(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let nombre_empleador = '';
                    let identificacion_empleador = '';
                    if (reg.get_empleador) {
                        identificacion_empleador = reg.get_empleador.emp_identificacion;
                        if (reg.get_empleador.emp_razon_social != '' && reg.get_empleador.emp_razon_social != null) {
                            nombre_empleador = reg.get_empleador.emp_razon_social;
                        } else {
                            nombre_empleador  = reg.get_empleador.emp_primer_nombre != null ? reg.get_empleador.emp_primer_nombre + ' ' : ' ';
                            nombre_empleador += reg.get_empleador.emp_otros_nombres != null ? reg.get_empleador.emp_otros_nombres + ' ' : ' ';
                            nombre_empleador += reg.get_empleador.emp_primer_apellido != null ? reg.get_empleador.emp_primer_apellido + ' ' : ' ';
                            nombre_empleador += reg.get_empleador.emp_segundo_apellido != null ? reg.get_empleador.emp_segundo_apellido : '';
                        }
                    }

                    this.registros.push(
                        {
                            'tra_id'             : reg.tra_id,
                            'nombre_empleador'   : nombre_empleador,
                            'tra_identificacion' : reg.tra_identificacion,
                            'tra_nombre_completo': reg.nombre_completo,
                            'emp_identificacion' : identificacion_empleador,
                            'estado'             : reg.estado
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los trabajadores', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            }
        );
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {*} $evt Acción del evento
     * @memberof TrabajadoresListarComponent
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
     * @memberof TrabajadoresListarComponent
     */
    onCheckboxChangeFn(evt: any) {}

    /**
     * Efectúa la carga de datos.
     *
     * @memberof TrabajadoresListarComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadTrabajadores();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {*} {selected} Registros seleccionados de la grid
     * @memberof TrabajadoresListarComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Permite recargar la lista del tracking.
     *
     * @memberof TrabajadoresListarComponent
     */
    recargarLista() {
        this.getData();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {*} accion Acción seleccionada
     * @memberof TrabajadoresListarComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Trabajador para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                
                let payload = [];
                this.selected.forEach(reg => {
                    payload.push({
                        'emp_identificacion': reg.emp_identificacion,
                        'tra_identificacion': reg.tra_identificacion
                    });
                });
                swal({
                    html: '¿Está seguro de cambiar el estado de los Trabajadores seleccionados?',
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
                        this._configuracionService.cambiarEstado(payload).subscribe(
                            response => {
                                this.loadTrabajadores();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros de los Trabajadores seleccionados han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof TrabajadoresListarComponent
     */
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGetTrabajadores(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de registros de Trabajadores', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Permite ir a la pantalla para subir los trabajadores.
     *
     * @memberof TrabajadoresListarComponent
     */
    subirTrabajadores() {
        this._router.navigate(['configuracion/nomina-electronica/trabajadores/subir-trabajadores']);
    }

    /**
     * ecarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar Valor a buscar
     * @memberof TrabajadoresListarComponent
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
     * @memberof TrabajadoresListarComponent
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
     * @memberof TrabajadoresListarComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'nombre_empleador':
                this.columnaOrden = 'nombre_empleador';
                break;
            case 'tra_identificacion':
                this.columnaOrden = 'identificacion';
                break;
            case 'tra_nombre_completo':
                this.columnaOrden = 'nombre_completo';
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
     * @memberof TrabajadoresListarComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof TrabajadoresListarComponent
     */
    onViewItem(item: any) {
        this._router.navigate(['configuracion/nomina-electronica/trabajadores/ver-trabajador/' + item.tra_identificacion  + '/' + item.tra_id + '/' + item.emp_identificacion]);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof TrabajadoresListarComponent
     */
    onRequestDeleteItem(item: any) { }

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof TrabajadoresListarComponent
     */
    onEditItem(item: any) {
        this._router.navigate(['configuracion/nomina-electronica/trabajadores/editar-trabajador/' + item.tra_identificacion + '/' + item.emp_identificacion]);
    }

    /**
     * Gestiona la acción del botón de cambiar estado de un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof TrabajadoresListarComponent
     */
    onCambiarEstadoItem(item){
        this.selected.push(item);
        this.accionesEnBloque('cambiarEstado');
    }

    /**
     * Aplica solamente a OFEs pero debe implementarse debido a la interface.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof TrabajadoresListarComponent
     */
    onRgEstandarItem(item: any) { }
}
