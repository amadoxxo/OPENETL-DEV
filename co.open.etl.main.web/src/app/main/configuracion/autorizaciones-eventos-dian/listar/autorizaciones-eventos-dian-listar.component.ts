import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Auth} from '../../../../services/auth/auth.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';
import {JwtHelperService} from '@auth0/angular-jwt';

@Component({
    selector: 'app-autorizaciones-eventos-dian-listar',
    templateUrl: './autorizaciones-eventos-dian-listar.component.html',
    styleUrls: ['./autorizaciones-eventos-dian-listar.component.scss']
})
export class AutorizacionesEventosDianListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    public trackingInterface: TrackingInterface;
    public loadingIndicator : any;
    public registros        : any [] = [];
    public usuario          : any;
    public aclsUsuario      : any;

    public columns: TrackingColumnInterface[] = [];

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
        private _configuracionService: ConfiguracionService,
        private jwtHelperService: JwtHelperService
    ) {
        super();
        this._configuracionService.setSlug = "autorizaciones-eventos-dian";
        this.trackingInterface = this;
        this.rows = [];

        this.usuario = this.jwtHelperService.decodeToken();
        this.columns = [
            {name: 'Receptor (OFE)', prop: 'receptor_ofe', sorteable: true, width: 250},
            {name: 'Proveedor', prop: 'proveedor', sorteable: true, width: 250},
            {name: this.usuario.grupos_trabajo.singular, prop: 'grupo_trabajo', sorteable: true, width: 150},
            {name: 'Identificación', prop: 'use_identificacion', sorteable: true, width: 120},
            {name: 'Nombres y Apellidos', prop: 'nombres_apellidos', sorteable: true, width: 250},
            {name: 'Email', prop: 'get_usuario_autorizacion_evento_dian.usu_email', sorteable: true, width: 250},
            {name: 'Estado Autorización', prop: 'estado', sorteable: true, width: 150},
            {name: 'Estado Usuario', prop: 'get_usuario_autorizacion_evento_dian.estado', sorteable: true, width: 120}
        ];
    }

    ngOnInit() {
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @private
     * @memberof AutorizacionesEventosDianListarComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadAutorizacionesEventosDian();
    }

    /**
     * Sobrescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Identifica si se debe generar el Excel
     * @return {*}  {string}
     * @memberof AutorizacionesEventosDianListarComponent
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
     * Permite ir a la pantalla para crear un nuevo usuario evento.
     *
     * @memberof AutorizacionesEventosDianListarComponent
     */
    nuevoAutorizacionesEventosDian() {
        this._router.navigate(['configuracion/autorizaciones-eventos-dian/nuevo-autorizaciones-eventos-dian']);
    }

    /**
     * Se encarga de traer la data de los diferentes registros.
     *
     * @memberof AutorizacionesEventosDianListarComponent
     */
    public loadAutorizacionesEventosDian(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let ofe               = reg.get_configuracion_obligado_facturar_electronicamente && reg.get_configuracion_obligado_facturar_electronicamente.nombre_completo ? reg.get_configuracion_obligado_facturar_electronicamente.nombre_completo : '';
                    let proveedor         = reg.get_configuracion_proveedor && reg.get_configuracion_proveedor.nombre_completo ? reg.get_configuracion_proveedor.nombre_completo : '';
                    let grupo_trabajo     = reg.get_configuracion_grupo_trabajo && reg.get_configuracion_grupo_trabajo.gtr_nombre ? reg.get_configuracion_grupo_trabajo.gtr_nombre : '';
                    let email             = reg.get_usuario_autorizacion_evento_dian ? reg.get_usuario_autorizacion_evento_dian.usu_email : '';
                    let estadoUsuario     = reg.get_usuario_autorizacion_evento_dian ? reg.get_usuario_autorizacion_evento_dian.estado : '';
                    let ofeId             = reg.get_configuracion_obligado_facturar_electronicamente ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion : '';
                    let proId             = reg.get_configuracion_proveedor ? reg.get_configuracion_proveedor.pro_identificacion : '';
                    let gtrId             = reg.get_configuracion_grupo_trabajo ? reg.get_configuracion_grupo_trabajo.gtr_codigo : '';
                    let use_identificador = ofeId + ':' + proId + ':' + gtrId + ':' + email;

                    this.registros.push(
                        {
                            'use_id'                                        : reg.use_id,
                            'use_identificador'                             : use_identificador,
                            'receptor_ofe'                                  : ofe,
                            'proveedor'                                     : proveedor,
                            'grupo_trabajo'                                 : grupo_trabajo,
                            'use_identificacion'                            : reg.use_identificacion,
                            'nombres_apellidos'                             : reg.use_nombres + ' ' + reg.use_apellidos,
                            'get_usuario_autorizacion_evento_dian.usu_email': email,
                            'estado'                                        : reg.estado,
                            'get_usuario_autorizacion_evento_dian.estado'   : estadoUsuario
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los usuarios autorizados eventos', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            }
        );
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {*} $evt Acción del evento
     * @memberof AutorizacionesEventosDianListarComponent
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
     * @memberof AutorizacionesEventosDianListarComponent
     */
    onCheckboxChangeFn(evt: any) {}

    /**
     * Efectúa la carga de datos.
     *
     * @memberof AutorizacionesEventosDianListarComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadAutorizacionesEventosDian();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {*} {selected} Registros seleccionados de la grid
     * @memberof AutorizacionesEventosDianListarComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Permite recargar la lista del tracking.
     *
     * @memberof AutorizacionesEventosDianListarComponent
     */
    recargarLista() {
        this.getData();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {*} accion Acción seleccionada
     * @memberof AutorizacionesEventosDianListarComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos una Autorización Evento DIAN para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let usuarios = '';
                this.selected.forEach(reg => {
                    usuarios += reg.use_id + ',';
                });
                usuarios = usuarios.slice(0, -1);
                swal({
                    html: '¿Está seguro de cambiar el estado de las Autorizaciones Eventos DIAN seleccionadas?',
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
                                this.loadAutorizacionesEventosDian();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros de Autorizaciones Eventos DIAN seleccionados han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof AutorizacionesEventosDianListarComponent
     */
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de registros de Autorizaciones Eventos DIAN', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Permite ir a la pantalla para subir los usuarios eventos.
     *
     * @memberof AutorizacionesEventosDianListarComponent
     */
    subirAutorizacionesEventosDian() {
        this._router.navigate(['configuracion/autorizaciones-eventos-dian/subir-autorizaciones-eventos-dian']);
    }

    /**
     * ecarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar Valor a buscar
     * @memberof AutorizacionesEventosDianListarComponent
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
     * @memberof AutorizacionesEventosDianListarComponent
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
     * @memberof AutorizacionesEventosDianListarComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'receptor_ofe':
                this.columnaOrden = 'ofe';
                break;
            case 'proveedor':
                this.columnaOrden = 'proveedor';
                break;
            case 'grupo_trabajo':
                this.columnaOrden = 'grupo_trabajo';
                break;
            case 'get_usuario_autorizacion_evento_dian.usu_identificacion':
                this.columnaOrden = 'identificacion';
                break;
            case 'nombres_apellidos':
                this.columnaOrden = 'nombres';
                break;
            case 'get_usuario_autorizacion_evento_dian.usu_email':
                this.columnaOrden = 'email';
                break;
            case 'estado':
                this.columnaOrden = 'estado';
                break;
            case 'get_usuario_autorizacion_evento_dian.estado':
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
     * @memberof AutorizacionesEventosDianListarComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof AutorizacionesEventosDianListarComponent
     */
    onViewItem(item: any) {
        this._router.navigate(['configuracion/autorizaciones-eventos-dian/ver-autorizaciones-eventos-dian/' + item.use_identificador  + '/' + item.use_id]);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {*} item
     * @memberof AutorizacionesEventosDianListarComponent
     */
    onRequestDeleteItem(item: any) { }

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof AutorizacionesEventosDianListarComponent
     */
    onEditItem(item: any) {
        this._router.navigate(['configuracion/autorizaciones-eventos-dian/editar-autorizaciones-eventos-dian/' + item.use_identificador]);
    }

    /**
     * Aplica solamente a OFEs pero debe implementarse debido a la interface.
     *
     * @param {*} item
     * @memberof AutorizacionesEventosDianListarComponent
     */
    onRgEstandarItem(item: any) { }
}
