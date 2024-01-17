import swal from 'sweetalert2';
import {Router} from '@angular/router';
import {Component, OnInit, Input} from '@angular/core';
import {BaseComponentList} from '../../../../core/base_component_list';
import {Auth} from '../../../../../services/auth/auth.service';
import {ConfiguracionService} from '../../../../../services/configuracion/configuracion.service';
import {AdquirentesService} from '../../../../../services/configuracion/adquirentes.service';
import {ReportesBackgroundService} from '../../../../../services/reportes/reportes_background.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'app-adquirentes-listar',
    templateUrl: './adquirentes-listar.component.html',
    styleUrls: ['./adquirentes-listar.component.scss']
})
export class AdquirentesListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    @Input() tipoAdquirente: string = null;
    @Input() permisoSubir: string = null;
    @Input() permisoDescargarExcel: string = null;
    @Input() permisoNuevo: string = null;
    @Input() permisoEditar: string = null;
    @Input() permisoVer: string = null;
    @Input() permisoCambiarEstado: string = null;

    public trackingInterface: TrackingInterface;
    public loadingIndicator: any;
    public accionesDescargar: any [] = [];
    public registros: any [] = [];
    public aclsUsuario: any;
    public tipo: string;
    titulo: string;
    tituloBoton: string;

    public columns: TrackingColumnInterface[] = [
        {name: 'Oferente', prop: 'oferente', sorteable: true, width: 120},
        {name: 'Identificación', prop: 'adq_identificacion', sorteable: true, width: 120},
        {name: 'ID', prop: 'adq_id_personalizado', sorteable: false, width: 120},
        {name: 'Razón Social', prop: 'adq_razon_social', sorteable: true, width: 200},
        {name: 'Nombre Comercial', prop: 'adq_nombre_comercial', sorteable: true, width: 200},
        {name: 'Nombre Completo', prop: 'nombre_completo', sorteable: true, width: 200},
        // {name: 'Tipo', prop: 'adq_tipo_adquirente', sorteable: true},
        {name: 'Estado', prop: 'estado', sorteable: true}
    ];

    public accionesBloque = [
        // {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    constructor(
        private _router: Router,
        public _auth: Auth,
        private _configuracionService: ConfiguracionService,
        private _adquirentesService: AdquirentesService,
        private _reportesBackgroundService: ReportesBackgroundService
    ) {
        super();
        this._configuracionService.setSlug = 'adquirentes';
        this.trackingInterface = this;
        this.rows = [];
    }

    ngOnInit() {
        this.tituloBoton = 'Nuevo';
        switch (this.tipoAdquirente) {
            case 'adquirente':
                this.trackingOpciones.portalesButton = true;
                this.titulo = 'Adquirentes';
                this._configuracionService.setSlug = 'adquirentes';
                this.accionesDescargar = [
                    {'id': 'excel_adquirentes', 'itemName': 'Descargar Excel Adquirentes'},
                    {'id': 'excel_usuarios_portal', 'itemName': 'Descargar Excel Usuarios Portal Clientes'}
                ];
                break;
            case 'autorizado':
                this.trackingOpciones.portalesButton = true;
                this.titulo = 'Autorizados (Representación)';
                this._configuracionService.setSlug = 'autorizados';
                this.accionesDescargar = [
                    {'id': 'excel_adquirentes', 'itemName': 'Descargar Excel'}
                ];
                break;
            case 'responsable':
                this.trackingOpciones.portalesButton = true;
                this.titulo = 'Responsables Entrega de Bienes';
                this._configuracionService.setSlug = 'responsables';
                this.accionesDescargar = [
                    {'id': 'excel_adquirentes', 'itemName': 'Descargar Excel'}
                ];
                break;
            case 'vendedor':
                this.trackingOpciones.portalesButton = false;
                this.titulo = 'Vendedores Documento Soporte';
                this._configuracionService.setSlug = 'vendedores-ds';
                this.accionesDescargar = [
                    {'id': 'excel_adquirentes', 'itemName': 'Descargar Excel'}
                ];
                break;
            default:
                break;
        }
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     * 
     */
    private init() {
        this.initDataSort('modificado');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadAdquirentes();
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
        if (excel) {
            query += '&excel=true';
        }
        // if (this.tipoAdquirente) {
        //     query += '&tipoAdquirente=' + this.tipoAdquirente;
        // }
        return query;
    }

    /**
     * Permite ir a la pantalla para crear un nuevo adquirente
     */
    nuevoAdquirente() {
        switch (this.tipoAdquirente) {
            case 'adquirente':
                this._router.navigate(['configuracion/adquirentes/nuevo-adquirente']);
                break;
            case 'autorizado':
                this._router.navigate(['configuracion/autorizados/nuevo-autorizado']);
                break;
            case 'responsable':
                this._router.navigate(['configuracion/responsables/nuevo-responsable']);
                break;
            case 'vendedor':
                this._router.navigate(['configuracion/vendedores/nuevo-vendedor']);
                break;
            default:
                break;
        }
    }

    /**
     * Permite ir a la pantalla para subir adquirentes
     */
    subirAdquirentes() {
        switch (this.tipoAdquirente) {
            case 'adquirente':
                this._router.navigate(['configuracion/adquirentes/subir-adquirentes']);
                break;
            case 'autorizado':
                this._router.navigate(['configuracion/autorizados/subir-autorizados']);
                break;
            case 'responsable':
                this._router.navigate(['configuracion/responsables/subir-responsables']);
                break;
            case 'vendedor':
                this._router.navigate(['configuracion/vendedores/subir-vendedores']);
                break;
            default:
                break;
        }
    }

    /**
     * Se encarga de traer la data de los diferentes registros.
     * 
     */
    public loadAdquirentes(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let razon_social_oferente = reg.get_configuracion_obligado_facturar_electronicamente ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_razon_social : '';
                    let identificacion_oferente = reg.get_configuracion_obligado_facturar_electronicamente ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion : '';
                    this.registros.push(
                        {
                            'adq_id'                     : reg.adq_id,
                            'ofe_id'                     : reg.ofe_id,
                            'oferente'                   : razon_social_oferente,
                            'ofe_identificacion'         : identificacion_oferente,
                            'adq_identificacion'         : reg.adq_identificacion,
                            'adq_id_personalizado'       : reg.adq_id_personalizado,
                            'adq_razon_social'           : reg.adq_razon_social,
                            'adq_nombre_comercial'       : reg.adq_nombre_comercial,
                            'nombre_completo'            : reg.nombre_completo,
                            // 'adq_tipo_adquirente'   : reg.adq_tipo_adquirente,
                            'usuarios_portales'          : reg.get_usuarios_portales,
                            'usuarios_portales_admitidos': res.cantidad_usuarios_portal_clientes,
                            'estado'                     : reg.estado
                        }
                    );
                });
                this.totalElements = res.filtrados;
                // this.totalElements = this.registros.length;
                this.loadingIndicator = false;
                this.totalShow = this.length !== -1 ? this.length : this.totalElements;
            },
            error => {
                let ruta = this.tipoAdquirente == 'vendedor' ? 'es' : 's';
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', `Error al cargar los ${this.capitalize(this.tipoAdquirente)}${ruta}`, '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
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
     * Método utilizado por los checkbox en los listados.
     * 
     * @param evt
     */
    onCheckboxChangeFn(evt: any) {

    }

    /**
     * Efectua la carga de datos.
     * 
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadAdquirentes();
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

    recargarLista() {
        this.getData();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {*} accion Acción a ejecutar
     * @memberof AdquirentesListarComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un ' + this.capitalize(this.tipoAdquirente) + ' para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let ruta = this.tipoAdquirente == 'vendedor' ? 'es' : 's';
                let payload = [];
                this.selected.forEach(reg => {
                    payload.push({
                        'ofe_identificacion'  : reg.ofe_identificacion,
                        'adq_identificacion'  : reg.adq_identificacion,
                        'adq_id_personalizado': reg.adq_id_personalizado
                    });
                });
                swal({
                    html: `¿Está seguro de cambiar el estado de los ${this.capitalize(this.tipoAdquirente)}${ruta} seleccionados?`,
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
                                let ruta = this.tipoAdquirente == 'vendedor' ? 'es' : 's';
                                this.loadAdquirentes();
                                this.loading(false);
                                this.showSuccess(`<h3>Los registros seleccionados de ${this.capitalize(this.tipoAdquirente)}${ruta} han cambiado de estado</h3>`, 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     */
    descargarExcelAdquirentes() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                let ruta = this.tipoAdquirente == 'vendedor' ? 'es' : 's';
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', `Error al descargar archivo excel de registros de ${this.capitalize(this.tipoAdquirente)}${ruta}`, 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Ejecuta la petición para agendar el proceso en background.
     *
     * @memberof AdquirentesListarComponent
     */
    agendarReporteBackground() {
        this.loading(true);
        let params = {
            tipo : 'adquirentes',
            json : JSON.stringify({
                start:  this.start,
                length: this.length,
                buscar: this.buscar,
                columnaOrden: this.columnaOrden,
                ordenDireccion: this.ordenDireccion,
                excel: true
            })
        };
        let peticion = this._reportesBackgroundService.agendarReporteExcel(params).toPromise()
            .then(resolve => {
                this.loading(false);
                swal({
                    type: 'success',
                    title: 'Proceso Exitoso',
                    html: resolve.message
                })
                .catch(swal.noop);
            })
            .catch(error => {
                this.loading(false);
                this.showError('No se agendó el reporte en background', 'error', 'Error al procesar la información', 'Ok', 'btn btn-danger');
            });
    }

    /**
     * Descarga un Excel con el listado de usuarios de portal clientes.
     *
     * @memberof AdquirentesListarComponent
     */
    descargarExcelUsuariosPortales() {
        this.loading(true);
        this._adquirentesService.descargarExcelUsuariosPortales(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de registros de Proveedores', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Direcciona al método correspondiente de acuerdo a la opción seleccionada en el combo de descargas de Excel.
     *
     * @param {string} opcion Acción a ejecutar
     * @memberof AdquirentesListarComponent
     */
    async descargasExcel(opcion: string) {
        switch(opcion) {
            case 'excel_adquirentes':
                await swal({
                    html: `¿Generar el reporte en background?`,
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonClass: 'btn btn-success',
                    confirmButtonText: 'Si',
                    cancelButtonText: 'No',
                    cancelButtonClass: 'btn btn-danger',
                    buttonsStyling: false,
                    allowOutsideClick: false
                })
                .then((result) => {
                    if (result.value) {
                        this.agendarReporteBackground();
                    } else {
                        this.descargarExcelAdquirentes();
                    }
                }).catch(swal.noop);
                break;
            case 'excel_usuarios_portal':
                this.descargarExcelUsuariosPortales();
                break;
        }
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
     * Realiza el ordenamiento de los registros y recarga el listado.
     * 
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'adq_identificacion':
                this.columnaOrden = 'identificacion';
                break;
            case 'adq_razon_social':
                this.columnaOrden = 'razon';
                break;
            case 'adq_nombre_comercial':
                this.columnaOrden = 'nombre';
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
        let adqIdPersonalizado = '';
        if(item.adq_id_personalizado !== '' && item.adq_id_personalizado !== null && item.adq_id_personalizado !== undefined)
            adqIdPersonalizado = '/' + item.adq_id_personalizado;

        switch (this.tipoAdquirente) {
            case 'adquirente':
                this._router.navigate(['configuracion/adquirentes/ver-adquirente/' + item.adq_identificacion + '/' + item.adq_id + '/' + item.ofe_identificacion + adqIdPersonalizado]);
                break;
            case 'autorizado':
                this._router.navigate(['configuracion/autorizados/ver-autorizado/' + item.adq_identificacion + '/' + item.adq_id + '/' + item.ofe_identificacion + adqIdPersonalizado]);
                break;
            case 'responsable':
                this._router.navigate(['configuracion/responsables/ver-responsable/' + item.adq_identificacion + '/' + item.adq_id + '/' + item.ofe_identificacion + adqIdPersonalizado]);
                break;
            case 'vendedor':
                this._router.navigate(['configuracion/vendedores/ver-vendedor/' + item.adq_identificacion + '/' + item.adq_id + '/' + item.ofe_identificacion + adqIdPersonalizado]);
                break;
            default:
                break;
        }
    }

    /**
     * Gestiona la acción del botón de eliminar un registro
     * 
     */
    onRequestDeleteItem(item: any) {
        
    }

    // Aplica solamente a OFEs pero debe implementarse debido a la interface
    onConfigurarDocumentoElectronico(item: any) {
        
    }

    /**
     * Gestiona la acción del botón de editar un registro
     * 
     */
    onEditItem(item: any) {
        let adqIdPersonalizado = '';
        if(item.adq_id_personalizado !== '' && item.adq_id_personalizado !== null && item.adq_id_personalizado !== undefined)
            adqIdPersonalizado = '/' + item.adq_id_personalizado;
        
        switch (this.tipoAdquirente) {
            case 'adquirente':
                this._router.navigate(['configuracion/adquirentes/editar-adquirente/' + item.adq_identificacion + '/' + item.ofe_identificacion + adqIdPersonalizado]);
                break;
            case 'autorizado':
                this._router.navigate(['configuracion/autorizados/editar-autorizado/' + item.adq_identificacion + '/' + item.ofe_identificacion + adqIdPersonalizado]);
                break;
            case 'responsable':
                this._router.navigate(['configuracion/responsables/editar-responsable/' + item.adq_identificacion + '/' + item.ofe_identificacion + adqIdPersonalizado]);
                break;
            case 'vendedor':
                this._router.navigate(['configuracion/vendedores/editar-vendedor/' + item.adq_identificacion + '/' + item.ofe_identificacion + adqIdPersonalizado]);
                break;
            default:
                break;
        }
    }
}
