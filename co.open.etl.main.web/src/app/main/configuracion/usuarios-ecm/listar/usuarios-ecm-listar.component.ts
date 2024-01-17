import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Auth} from '../../../../services/auth/auth.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'app-usuarios-ecm-listar',
    templateUrl: './usuarios-ecm-listar.component.html',
    styleUrls: ['./usuarios-ecm-listar.component.scss']
})
export class UsuariosEcmListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    public trackingInterface: TrackingInterface;
    public loadingIndicator: any;
    public registros: any [] = [];
    public aclsUsuario: any;

    public columns: TrackingColumnInterface[] = [
        {name: 'Identificación',       prop: 'get_usuario_ecm.usu_identificacion',  sorteable: true, width: 120},
        {name: 'Nombres y Apellidos',  prop: 'get_usuario_ecm.usu_nombre',          sorteable: true, width: 120},
        {name: 'Email',                prop: 'get_usuario_ecm.usu_email',           sorteable: true, width: 130},
        {name: 'Estado openECM',       prop: 'estado',                              sorteable: true, width: 130},
        {name: 'Estado',               prop: 'get_usuario_ecm.estado',              sorteable: true, width: 120}
    ];

    public accionesBloque = [
        // {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    constructor(
        private _router:Router,
        public _auth: Auth,
        private _configuracionService: ConfiguracionService) {
        super();
        this._configuracionService.setSlug = "usuarios-ecm";
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
     * @memberof UsuariosEcmListarComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadUsuarioEcm();
    }

    /**
     * Sobrescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Identifica si se debe generar el Excel
     * @return {*}  {string}
     * @memberof UsuariosEcmListarComponent
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
     * Permite ir a la pantalla para crear un nuevo usuario de openECM.
     *
     * @memberof UsuariosEcmListarComponent
     */
    nuevoUsuarioEcm() {
        this._router.navigate(['configuracion/usuarios-ecm/nuevo-usuario-ecm']);
    }

    /**
     * Se encarga de traer la data de los diferentes registros.
     *
     * @memberof UsuariosEcmListarComponent
     */
    public loadUsuarioEcm(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let receptor = reg.get_configuracion_obligado_facturar_electronicamente ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_razon_social : '';
                    if (receptor === '' && reg.get_configuracion_obligado_facturar_electronicamente)
                        receptor = reg.get_configuracion_obligado_facturar_electronicamente.ofe_primer_nombre + ' ' + reg.get_configuracion_obligado_facturar_electronicamente.ofe_primer_apellido;

                    let identificacion    = reg.get_usuario_ecm ? reg.get_usuario_ecm.usu_identificacion : '';
                    let email             = reg.get_usuario_ecm ? reg.get_usuario_ecm.usu_email : '';
                    let nombres_apellidos = reg.get_usuario_ecm ? reg.get_usuario_ecm.usu_nombre : '';
                    let estadoUsuario     = reg.get_usuario_ecm ? reg.get_usuario_ecm.estado : '';
                    let ofeId             = reg.get_configuracion_obligado_facturar_electronicamente ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion : '';
                    let usuId             = reg.get_usuario_ecm ? reg.get_usuario_ecm.usu_identificacion : '';
                    let use_identificador = identificacion;

                    this.registros.push(
                        {
                            'use_id': reg.use_id,
                            'use_identificador': use_identificador,
                            'get_configuracion_obligado_facturar_electronicamente.ofe_razon_social': receptor,
                            'get_usuario_ecm.usu_identificacion': identificacion,
                            'get_usuario_ecm.usu_nombre': nombres_apellidos,
                            'get_usuario_ecm.usu_email': email,
                            'estado': reg.estado,
                            'get_usuario_ecm.estado': estadoUsuario
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los usuarios de openECM', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            }
        );
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {*} $evt Acción del evento
     * @memberof UsuariosEcmListarComponent
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
     * @memberof UsuariosEcmListarComponent
     */
    onCheckboxChangeFn(evt: any) {}

    /**
     * Efectúa la carga de datos.
     *
     * @memberof UsuariosEcmListarComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadUsuarioEcm();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {*} {selected} Registros seleccionados de la grid
     * @memberof UsuariosEcmListarComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Permite recargar la lista del tracking.
     *
     * @memberof UsuariosEcmListarComponent
     */
    recargarLista() {
        this.getData();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {*} accion Acción seleccionada
     * @memberof UsuariosEcmListarComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Usuario de openECM para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let usuarios = '';

                this.selected.forEach(reg => {
                    usuarios += reg.use_identificador + ',';
                });
                usuarios = usuarios.slice(0, -1);
                swal({
                    html: '¿Está seguro de cambiar el estado de los Usuarios de openECM seleccionados?',
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
                            'usuarios': usuarios
                        }
                        this._configuracionService.cambiarEstado(payload).subscribe(
                            response => {
                                this.loadUsuarioEcm();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros de Usuarios de openECM seleccionados han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof UsuariosEcmListarComponent
     */
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de registros de Usuarios openECM', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Permite ir a la pantalla para subir los usuarios de openECM.
     *
     * @memberof UsuariosEcmListarComponent
     */
    subirUsuariosEcm() {
        this._router.navigate(['configuracion/usuarios-ecm/subir-usuarios-ecm']);
    }

    /**
     * ecarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar Valor a buscar
     * @memberof UsuariosEcmListarComponent
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
     * @memberof UsuariosEcmListarComponent
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
     * @memberof UsuariosEcmListarComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'get_usuario_ecm.usu_identificacion':
                this.columnaOrden = 'identificacion';
                break;
            case 'get_usuario_ecm.usu_nombre':
                this.columnaOrden = 'nombres';
                break;
            case 'get_usuario_ecm.usu_email':
                this.columnaOrden = 'email';
                break;
            case 'estado':
                this.columnaOrden = 'estado';
                break;
            case 'get_usuario_ecm.estado':
                this.columnaOrden = 'estado_usuario';
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
     * @memberof UsuariosEcmListarComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof UsuariosEcmListarComponent
     */
    onViewItem(item: any) {
        this._router.navigate(['configuracion/usuarios-ecm/ver-usuario-ecm/' + item.use_identificador  + '/' + item.use_id]);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {*} item
     * @memberof UsuariosEcmListarComponent
     */
    onRequestDeleteItem(item: any) { }

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof UsuariosEcmListarComponent
     */
    onEditItem(item: any) {
        this._router.navigate(['configuracion/usuarios-ecm/editar-usuario-ecm/' + item.use_identificador]);
    }

    /**
     * Aplica solamente a OFEs pero debe implementarse debido a la interface.
     *
     * @param {*} item
     * @memberof UsuariosEcmListarComponent
     */
    onRgEstandarItem(item: any) { }
}
