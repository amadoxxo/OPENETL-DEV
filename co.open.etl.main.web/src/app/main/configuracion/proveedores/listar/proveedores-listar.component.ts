import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Auth} from '../../../../services/auth/auth.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {ProveedoresService} from '../../../../services/configuracion/proveedores.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'app-proveedores-listar',
    templateUrl: './proveedores-listar.component.html',
    styleUrls: ['./proveedores-listar.component.scss']
})
export class ProveedoresListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    public trackingInterface   : TrackingInterface;
    public loadingIndicator    : any;
    public registros           : any [] = [];
    public aclsUsuario         : any;
    public titulo              : string;
    public tituloBoton         : string;

    public columns: TrackingColumnInterface[] = [
        {name: 'OFE', prop: 'ofe_razon_social', sorteable: true, width: 200},
        {name: 'Identificación', prop: 'pro_identificacion', sorteable: true, width: 100},
        {name: 'Razón Social', prop: 'pro_razon_social', sorteable: true, width: 200},
        {name: 'Estado', prop: 'estado', sorteable: true, width: 50}
    ];

    public accionesBloque    = [];
    public accionesDescargar = [
        {'id': 'excel_proveedores', 'itemName': 'Descargar Excel Proveedores'},
        {'id': 'excel_usuarios_portal', 'itemName': 'Descargar Excel Usuarios Portal Proveedores'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true,
        portalesButton: true
    };

    constructor(
        private _router:Router,
        public _auth: Auth,
        private _configuracionService: ConfiguracionService,
        private _proveedoresService: ProveedoresService
    ) {
        super();
        this._configuracionService.setSlug = "proveedores";
        this.trackingInterface = this;
        this.rows = [];
    }

    ngOnInit() {
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
        this.loadProveedores();
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
     * Permite ir a la pantalla para crear un nuevo proveedor
     */
    nuevoProveedor() {
        this._router.navigate(['configuracion/proveedores/nuevo-proveedor']);
    }

    /**
     * Permite ir a la pantalla para subir proveedores
     */
    subirProveedores() {
        this._router.navigate(['configuracion/proveedores/subir-proveedores']);
    }

    /**
     * Se encarga de traer la data de los diferentes registros.
     * 
     */
    public loadProveedores(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'pro_id'                     : reg.pro_id,
                            'ofe_id'                     : reg.ofe_id,
                            'ofe_razon_social'           : reg.get_configuracion_obligado_facturar_electronicamente.ofe_razon_social,
                            'ofe_identificacion'         : reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion,
                            'pro_identificacion'         : reg.pro_identificacion,
                            'pro_razon_social'           : reg.pro_razon_social,
                            'pro_nombre_comercial'       : reg.pro_nombre_comercial,
                            'usuarios_portales'          : reg.get_usuarios_portales,
                            'usuarios_portales_admitidos': res.cantidad_usuarios_portal_proveedores,
                            'estado'                     : reg.estado
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Proveedores', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
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
        this.loadProveedores();
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
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Proveedor para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let payload = [];
                this.selected.forEach(reg => {
                    let ofeYaTieneKey = false;
                    payload.forEach(el => {
                        if(el.ofe_identificacion === reg.ofe_identificacion) {
                            el.pro_identificacion += reg.pro_identificacion + ',';
                            ofeYaTieneKey = true;
                        }
                    });
                    if(!ofeYaTieneKey){
                        payload.push({
                            'ofe_identificacion' : reg.ofe_identificacion,
                            'pro_identificacion' : reg.pro_identificacion + ','
                        });
                    }
                });
                payload.forEach(el => {
                    el.pro_identificacion = el.pro_identificacion.slice(0, -1);
                });
    
                swal({
                    html: '¿Está seguro de cambiar el estado de los Proveedores seleccionados?',
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
                                this.loadProveedores();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros de Proveedores han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
    descargarExcelProveedores() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
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
     * Descarga un Excel con el listado de usuarios de portal proveedores.
     *
     * @memberof ProveedoresListarComponent
     */
    descargarExcelUsuariosPortales() {
        this.loading(true);
        this._proveedoresService.descargarExcelUsuariosPortales(this.getSearchParametersInline(true)).subscribe(
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
     * Direcciona al método correspondiente de acuerdo a la opción seleccionada en el combo de descargas de Excel
     *
     * @param {string} opcion
     * @memberof ProveedoresListarComponent
     */
    descargasExcel(opcion) {
        switch(opcion) {
            case 'excel_proveedores':
                this.descargarExcelProveedores();
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
            case 'pro_identificacion':
                this.columnaOrden = 'identificacion';
                break;
            case 'pro_razon_social':
                this.columnaOrden = 'razon';
                break;
            case 'pro_nombre_comercial':
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
        this._router.navigate(['configuracion/proveedores/ver-proveedor/' + item.pro_identificacion + '/' + item.pro_id + '/' + item.ofe_identificacion]);
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
        this._router.navigate(['configuracion/proveedores/editar-proveedor/' + item.pro_identificacion + '/' + item.ofe_identificacion]);
    }

    // Aplica solamente a OFEs pero debe implementarse debido a la interface
    onConfigurarDocumentoElectronico(item: any) {
        
    }
}
