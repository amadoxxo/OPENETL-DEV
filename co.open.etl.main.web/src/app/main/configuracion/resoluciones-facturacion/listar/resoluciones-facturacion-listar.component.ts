import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Auth} from '../../../../services/auth/auth.service';
import * as moment from 'moment';
import {CommonsService} from 'app/services/commons/commons.service';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';
import {ModalConsultaResolucionDianComponent} from './../../../commons/modal-consulta-resolucion-dian/modal-consulta-resolucion-dian.component';

@Component({
    selector: 'app-resoluciones-facturacion-listar',
    templateUrl: './resoluciones-facturacion-listar.component.html',
    styleUrls: ['./resoluciones-facturacion-listar.component.scss']
})
export class ResolucionesFacturacionListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    public trackingInterface: TrackingInterface;
    public loadingIndicator: any;
    public registros: any [] = [];
    public aclsUsuario: any;

    public columns: TrackingColumnInterface[] = [
        {name: 'OFE', prop: 'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion', sorteable: true, width: 120},
        {name: 'Tipo', prop: 'rfa_tipo', sorteable: true, width: 120},
        {name: 'Resolución', prop: 'rfa_resolucion', sorteable: true, width: 120},
        {name: 'Prefijo', prop: 'rfa_prefijo', sorteable: true, width: 100},
        {name: 'Vigencia Desde', prop: 'rfa_fecha_desde', sorteable: true, width: 130},
        {name: 'Vigencia Hasta', prop: 'rfa_fecha_hasta', sorteable: true, width: 130},
        {name: 'Consecutivo Inicial', prop: 'rfa_consecutivo_inicial', sorteable: true, width: 140},
        {name: 'Consecutivo Final', prop: 'rfa_consecutivo_final', sorteable: true, width: 140},
        {name: 'Estado', prop: 'estado', sorteable: true, width: 120}
    ];

    public accionesBloque = [
        // {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    private modalConsultaResolucionDian: any;

    constructor(
        public _auth: Auth,
        private _router:Router,
        private _modal: MatDialog,
        private _commonsService: CommonsService,
        private _configuracionService: ConfiguracionService
    ) {
        super();
        this._configuracionService.setSlug = "resoluciones-facturacion";
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
        this.initDataSort('resolucion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadRFAs();
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
     * Permite ir a la pantalla para crear una nueva resolución de facturación
     */
    nuevaRFA() {
        this._router.navigate(['configuracion/resoluciones-facturacion/nueva-resolucion-facturacion']);
    }

    /**
     * Se encarga de traer la data de los diferentes registros.
     * 
     */
    public loadRFAs(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let ofe_id = reg.get_configuracion_obligado_facturar_electronicamente ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_id : '';
                    let ofe_identificacion = reg.get_configuracion_obligado_facturar_electronicamente ? reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion : '';
                    let prefijo = reg.rfa_prefijo ? reg.rfa_prefijo : '';
                    let rfa_tipo_prefijo_resolucion = ofe_identificacion + ':' + reg.rfa_tipo;
                    rfa_tipo_prefijo_resolucion = (prefijo === '') ? rfa_tipo_prefijo_resolucion + ':' + reg.rfa_resolucion : rfa_tipo_prefijo_resolucion + ':' + prefijo + ':' + reg.rfa_resolucion;
                    this.registros.push(
                        {
                            'get_configuracion_obligado_facturar_electronicamente.ofe_id': ofe_id,
                            'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion': ofe_identificacion,
                            'rfa_id': reg.rfa_id,
                            'rfa_tipo': reg.rfa_tipo,
                            'rfa_resolucion': reg.rfa_resolucion,
                            'rfa_tipo_prefijo_resolucion': rfa_tipo_prefijo_resolucion,
                            'rfa_prefijo': prefijo,
                            'rfa_fecha_desde': moment(reg.rfa_fecha_desde).format('YYYY-MM-DD'),
                            'rfa_fecha_hasta': moment(reg.rfa_fecha_hasta).format('YYYY-MM-DD'),
                            'rfa_consecutivo_inicial': reg.rfa_consecutivo_inicial,
                            'rfa_consecutivo_final': reg.rfa_consecutivo_final,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar las Resoluciones de Facturación', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
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
        this.loadRFAs();
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
                this.showError('<h3>Debe seleccionar al menos una Resolución de Facturación para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let resoluciones = '';
                this.selected.forEach(reg => {
                    resoluciones += reg.rfa_tipo_prefijo_resolucion + ',';
                });
                resoluciones = resoluciones.slice(0, -1);
                swal({
                    html: '¿Está seguro de cambiar el estado de las Resoluciones de Facturación seleccionadas?',
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
                            'resoluciones': resoluciones
                        }
                        this._configuracionService.cambiarEstado(payload).subscribe(
                            response => {
                                this.loadRFAs();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros de Resoluciones de Facturación seleccionados han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de registros de Resoluciones de Facturación', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Permite ir a la pantalla para subir resoluciones facturación.
     * 
     */
    subirRFAs() {
        this._router.navigate(['configuracion/resoluciones-facturacion/subir-resoluciones-facturacion']);
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
            case 'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion':
                this.columnaOrden = 'ofe';
                break;
            case 'rfa_resolucion':
                this.columnaOrden = 'resolucion';
                break;
            case 'rfa_prefijo':
                this.columnaOrden = 'prefijo';
                break;
            case 'rfa_fecha_desde':
                this.columnaOrden = 'fecha_desde';
                break;
            case 'rfa_fecha_hasta':
                this.columnaOrden = 'fecha_hasta';
                break;
            case 'rfa_consecutivo_inicial':
                this.columnaOrden = 'consecutivo_inicial';
                break;
            case 'rfa_consecutivo_final':
                this.columnaOrden = 'consecutivo_final';
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
        this._router.navigate(['configuracion/resoluciones-facturacion/ver-resolucion-facturacion/' + item.rfa_tipo_prefijo_resolucion + '/' + item.rfa_id]);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro
     * 
     */
    onRequestDeleteItem(item: any) { }

    /**
     * Gestiona la acción del botón de editar un registro
     * 
     */
    onEditItem(item: any) {
        this._router.navigate(['configuracion/resoluciones-facturacion/editar-resolucion-facturacion/' + item.rfa_tipo_prefijo_resolucion]);
    }

    // Aplica solamente a OFEs pero debe implementarse debido a la interface
    onConfigurarDocumentoElectronico(item: any) { }

    /**
     * Abre la modal para permitir la consulta en la DIAN de las resoluciones de facturación de un OFE.
     *
     * @memberof ResolucionesFacturacionListarComponent
     */
    consultarResolucionesFacturacion() {
        this.loading(true);
        let ofes = [];
        let registro;
        this._commonsService.getDataInitForBuild('tat=false').subscribe(
            result => {
                ofes = result.data.ofes;
                this.loading(false);
                const modalConfig = new MatDialogConfig();
                modalConfig.autoFocus = true;
                modalConfig.width = '600px';
                modalConfig.data = {
                    ofes: ofes
                };
                this.modalConsultaResolucionDian = this._modal.open(ModalConsultaResolucionDianComponent, modalConfig);
            }, error => {
                this.loading(false);
                this.showError('<h4>' + error.message + '</h4>', 'error', 'Error al cargar los ofes', 'Ok', 'btn btn-danger');
            }
        );
    }
}
