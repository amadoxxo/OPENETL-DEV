import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Auth} from '../../../../services/auth/auth.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'app-administracion-recepcion-erp-listar',
    templateUrl: './administracion-recepcion-erp-listar.component.html',
    styleUrls: ['./administracion-recepcion-erp-listar.component.scss']
})
export class AdministracionRecepcionErpListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    public trackingInterface: TrackingInterface;
    public loadingIndicator: any;
    public registros: any [] = [];
    public aclsUsuario: any;

    public columns: TrackingColumnInterface[] = [
        {name: 'OFE - Receptor', prop: 'receptor',   sorteable: true, width: 120},
        {name: 'ERP',            prop: 'erp',        sorteable: true, width: 120},
        {name: 'Regla',          prop: 'regla',      sorteable: true, width: 110},
        {name: 'Creado',         prop: 'creado',     sorteable: true, width: 100},
        {name: 'Modificado',     prop: 'modificado', sorteable: true, width: 100},
        {name: 'Estado',         prop: 'estado',     sorteable: true, width: 90}
    ];

    public accionesBloque = [
        // {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
    ];
m
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
        this._configuracionService.setSlug = "administracion-recepcion-erp";
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
     * @memberof AdministracionRecepcionErpListarComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadAdministracionRecepcionErp();
    }

    /**
     * Sobrescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Identifica si se debe generar el Excel
     * @return {*}  {string}
     * @memberof AdministracionRecepcionErpListarComponent
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
     * Permite ir a la pantalla para crear una nueva administración recepción ERP.
     *
     * @memberof AdministracionRecepcionErpListarComponent
     */
    nuevoAdministracionRecepcionErp() {
        this._router.navigate(['configuracion/administracion-recepcion-erp/nuevo-administracion-recepcion-erp']);
    }

    /**
     * Se encarga de traer la data de los registros de administración recepción ERP.
     *
     * @memberof AdministracionRecepcionErpListarComponent
     */
    public loadAdministracionRecepcionErp(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let nombre_ofe = '';
                    if (reg.get_configuracion_obligado_facturar_electronicamente) {
                        if (reg.get_configuracion_obligado_facturar_electronicamente.ofe_razon_social != '' && reg.get_configuracion_obligado_facturar_electronicamente.ofe_razon_social != null) {
                            nombre_ofe = reg.get_configuracion_obligado_facturar_electronicamente.ofe_razon_social;
                        } else {
                            nombre_ofe  = reg.get_configuracion_obligado_facturar_electronicamente.ofe_primer_nombre != null ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_primer_nombre + ' ' : ' ';
                            nombre_ofe += reg.get_configuracion_obligado_facturar_electronicamente.ofe_otros_nombres != null ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_otros_nombres + ' ' : ' ';
                            nombre_ofe += reg.get_configuracion_obligado_facturar_electronicamente.ofe_primer_apellido != null ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_primer_apellido + ' ' : ' ';
                            nombre_ofe += reg.get_configuracion_obligado_facturar_electronicamente.ofe_segundo_apellido != null ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_segundo_apellido : '';
                        }
                    }

                    this.registros.push(
                        {
                            'ate_id'     : reg.ate_id,
                            'receptor'   : nombre_ofe,
                            'erp'        : reg.ate_erp,
                            'regla'      : reg.ate_descripcion,
                            'creado'     : reg.fecha_creacion,
                            'modificado' : reg.fecha_modificacion,
                            'estado'     : reg.estado,
                            'ate_grupo'  : reg.ate_grupo
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar la Administración Recepción ERP', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            }
        );
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {*} $evt Acción del evento
     * @memberof AdministracionRecepcionErpListarComponent
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
     * @memberof AdministracionRecepcionErpListarComponent
     */
    onCheckboxChangeFn(evt: any) {}

    /**
     * Efectúa la carga de datos.
     *
     * @memberof AdministracionRecepcionErpListarComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadAdministracionRecepcionErp();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {*} {selected} Registros seleccionados de la grid
     * @memberof AdministracionRecepcionErpListarComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Permite recargar la lista del tracking.
     *
     * @memberof AdministracionRecepcionErpListarComponent
     */
    recargarLista() {
        this.getData();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {*} accion Acción seleccionada
     * @memberof AdministracionRecepcionErpListarComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos una Administración Recepción ERP para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let arrRegistros = [];
                this.selected.forEach(reg => {
                    let ObjRegistros = new Object;
                    ObjRegistros['ate_grupo'] = reg.ate_grupo;

                    arrRegistros.push(ObjRegistros);
                });
                swal({
                    html: '¿Está seguro de cambiar el estado de la Administración Recepción ERP seleccionados?',
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
                        this._configuracionService.cambiarEstado(arrRegistros).subscribe(
                            response => {
                                this.loadAdministracionRecepcionErp();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros de la Administración Recepción ERP seleccionados han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof AdministracionRecepcionErpListarComponent
     */
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de registros de Administración Recepción ERP', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * ecarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar Valor a buscar
     * @memberof AdministracionRecepcionErpListarComponent
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
     * @memberof AdministracionRecepcionErpListarComponent
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
     * @memberof AdministracionRecepcionErpListarComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'receptor':
                this.columnaOrden = 'receptor';
                break;
            case 'erp':
                this.columnaOrden = 'erp';
                break;
            case 'regla':
                this.columnaOrden = 'regla';
                break;
            case 'creado':
                this.columnaOrden = 'creado';
                break;
            case 'modificado':
                this.columnaOrden = 'modificado';
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
     * @memberof AdministracionRecepcionErpListarComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof AdministracionRecepcionErpListarComponent
     */
    onViewItem(item: any) {
        this._router.navigate(['configuracion/administracion-recepcion-erp/ver-administracion-recepcion-erp/' + item.ate_grupo]);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof AdministracionRecepcionErpListarComponent
     */
    onRequestDeleteItem(item: any) { }

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof AdministracionRecepcionErpListarComponent
     */
    onEditItem(item: any) {
        this._router.navigate(['configuracion/administracion-recepcion-erp/editar-administracion-recepcion-erp/' + item.ate_grupo]);
    }

    /**
     * Gestiona la acción del botón de cambiar estado de un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof AdministracionRecepcionErpListarComponent
     */
    onCambiarEstadoItem(item){
        this.selected.push(item);
        this.accionesEnBloque('cambiarEstado');
    }

    /**
     * Aplica solamente a OFEs pero debe implementarse debido a la interface.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof AdministracionRecepcionErpListarComponent
     */
    onRgEstandarItem(item: any) { }
}
