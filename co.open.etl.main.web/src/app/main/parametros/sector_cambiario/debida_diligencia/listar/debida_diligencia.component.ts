import {Component} from '@angular/core';
import {BaseComponentList} from '../../../../core/base_component_list';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../../services/auth/auth.service';
import {MatDialog, MatDialogConfig, MatDialogRef} from '@angular/material/dialog';
import {DebidaDiligenciaGestionarComponent} from '../gestionar/debida_diligencia_gestionar.component';
import {ParametrosService} from '../../../../../services/parametros/parametros.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../../commons/open-tracking/tracking-interface';

interface InterfaceDebidaDiligencia {
    ddi_id               : number;
    ddi_codigo           : string;
    ddi_descripcion      : string;
    fecha_vigencia_desde?: string|null,
    fecha_vigencia_hasta?: string|null,
    estado              ?: 'ACTIVO'|'INACTIVO'
}

interface Event {
    count: number;
    limit: number;
    offset: number;
    pageSize: number;
}

@Component({
    selector: 'debida-diligencia',
    templateUrl: './debida_diligencia.component.html',
    styleUrls: ['./debida_diligencia.component.scss']
})
export class DebidaDiligenciaComponent extends BaseComponentList implements TrackingInterface {
    
    public  loadingIndicator: boolean;
    private modalDebidaDiligencia: MatDialogRef<DebidaDiligenciaGestionarComponent, any>;
    public  aclsUsuario: {roles: object, permisos: object};

    public trackingInterface: TrackingInterface;

    public registros:InterfaceDebidaDiligencia[] = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Código',         prop: 'ddi_codigo',           sorteable: true, width: 100},
        {name: 'Descripción',    prop: 'ddi_descripcion',      sorteable: true},
        {name: 'Vigencia Desde', prop: 'fecha_vigencia_desde', sorteable: true, width: 150},
        {name: 'Vigencia Hasta', prop: 'fecha_vigencia_hasta', sorteable: true, width: 150},
        {name: 'Estado',         prop: 'estado',               sorteable: true, width: 100}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    /**
     * Crea una instancia de DebidaDiligenciaComponent.
     * 
     * @param {Router} router
     * @param {Auth} auth
     * @param {MatDialog} modal
     * @param {ParametrosService} parametrosService
     * @memberof DebidaDiligenciaComponent
     */
    constructor(
        private router: Router,
        public  auth: Auth,
        private modal: MatDialog,
        private parametrosService: ParametrosService
    ) {
        super();
        this.parametrosService.setSlug = "debida-diligencia";
        this.trackingInterface = this;
        this.rows = [];
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @private
     * @memberof DebidaDiligenciaComponent
     */
    private init(): void {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this.auth.getAcls();
        this.loadDebidaDiligencia();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false]
     * @param {boolean} [tracking=true]
     * @return {string}
     * @memberof DebidaDiligenciaComponent
     */
    public getSearchParametersInline(excel = false, tracking = true): string {
		let query = 'start=' + this.start + '&' +
		'length=' + this.length + '&' +
		'buscar=' + this.buscar + '&' +
		'columnaOrden=' + this.columnaOrden + '&' +
		'ordenDireccion=' + this.ordenDireccion;
		if (excel)
			query += '&excel=true';
		if (tracking)
			query += '&tracking=true';

		return query;
    }

    /**
     *  Se encarga de traer la data de los diferentes registros.
     *
     * @memberof DebidaDiligenciaComponent
     */
    public loadDebidaDiligencia(): void {
        this.loading(true);
        this.parametrosService.listar(this.getSearchParametersInline()).subscribe({
            next: res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach((reg: InterfaceDebidaDiligencia) => {
                    this.registros.push(
                        {
                            'ddi_id': reg.ddi_id,
                            'ddi_codigo': reg.ddi_codigo,
                            'ddi_descripcion': reg.ddi_descripcion,
                            'fecha_vigencia_desde': reg.fecha_vigencia_desde,
                            'fecha_vigencia_hasta': reg.fecha_vigencia_hasta,
                            'estado': reg.estado
                        }
                    );
                });
                this.totalElements = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow = this.length !== -1 ? this.length : this.totalElements;
            },
            error: error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar la Debida Diligencia', '0k, entiendo', 'btn btn-danger', '/dashboard', this.router);
            }
        });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {Event} evt Evento de paginación
     * @memberof DebidaDiligenciaComponent
     */
    public onPage(evt: Event): void {
        this.page = evt.offset;
        this.start = evt.offset * this.length;
        this.selected = [];
        this.getData();
    }

    /**
     * Método utilizado por los checkbox en los listados.
     *
     * @param {Event} evt Evento de check
     * @memberof DebidaDiligenciaComponent
     */
    public onCheckboxChangeFn(evt: Event): void {}

    /**
     * Efectua la carga de datos.
     *
     * @memberof DebidaDiligenciaComponent
     */
    public getData(): void {
        this.loadingIndicator = true;
        this.loadDebidaDiligencia();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {object[]} selected Registros seleccionados
     * @memberof DebidaDiligenciaComponent
     */
    onSelect(selected: object[]): void {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Metodo utilizado para recargar la lista del tracking.
     *
     * @memberof DebidaDiligenciaComponent
     */
    recargarLista(): void {
        this.getData();
    }

    /**
     * Apertura una ventana modal para crear o editar un registro.
     *
     * @param {string} action Acción a realizar
     * @param {number} ddi_id Id del registro
     * @param {string} ddi_codigo Código del registro
     * @param {string} ddi_descripcion Descripción del registro
     * @param {(string|null)} [fecha_desde=null] Fecha de vigencia desde del registro
     * @param {(string|null)} [fecha_hasta=null] Fecha de vigencia hasta del registro
     * @memberof DebidaDiligenciaComponent
     */
    public openmodalDebidaDiligencia(action: string, ddi_id: number, ddi_codigo: string, ddi_descripcion: string, fecha_desde:string|null = null, fecha_hasta:string|null = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            ddi_id: ddi_id,
            ddi_codigo: ddi_codigo,
            ddi_descripcion: ddi_descripcion,
            fecha_vigencia_desde: fecha_desde,
            fecha_vigencia_hasta: fecha_hasta
        };
        this.modalDebidaDiligencia = this.modal.open(DebidaDiligenciaGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un registro.
     *
     * @memberof DebidaDiligenciaComponent
     */
    public closemodalDebidaDiligencia(): void {
        if (this.modalDebidaDiligencia) {
            this.modalDebidaDiligencia.close();
            this.modalDebidaDiligencia = null;
        }
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {string} accion
     * @memberof DebidaDiligenciaComponent
     */
    public accionesEnBloque(accion: string): void {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos una Debida Diligencia para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                const arrCodigos: InterfaceDebidaDiligencia[] = [];
                this.selected.forEach((reg: InterfaceDebidaDiligencia) => {
                    const { ddi_id ,ddi_codigo, ddi_descripcion, fecha_vigencia_desde, fecha_vigencia_hasta } = reg;
                    arrCodigos.push({
                        ddi_id,
                        ddi_codigo,
                        ddi_descripcion,
                        fecha_vigencia_desde,
                        fecha_vigencia_hasta
                    });
                });
                swal({
                    html: '¿Está seguro de cambiar el estado de las Debidas Diligencias seleccionadas?',
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
                        this.parametrosService.cambiarEstadoCodigos(arrCodigos).subscribe({
                            next: () => {
                                this.loadDebidaDiligencia();
                                this.loading(false);
                                this.showSuccess('<h3>Las Debidas Diligencias han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
                            },
                            error: error => {
                                this.loading(false);
                                const texto_errores = this.parseError(error);
                                this.loadingIndicator = false;
                                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cambiar de estado', 'OK', 'btn btn-danger');
                            }
                        });
                    }
                }).catch(swal.noop);
            }
        } 
        this.selected = [];
    }

    /**
     * Descarga los registros filtrados en un archivos de excel.
     * 
     * @memberof DebidaDiligenciaComponent
     */
    public descargarExcel(): void {
        this.loading(true);
        this.parametrosService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe({
            next: () => {
                this.loading(false);
            },
            error: () => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de Debida Diligencia', 'OK', 'btn btn-danger');
            }
        });
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar
     * @memberof DebidaDiligenciaComponent
     */
    public onSearchInline(buscar: string): void {
        this.start = 0;
        this.buscar = buscar;
        this.recargarLista();
    }


    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     *
     * @param {number} size Cantidad de registros a mostrar
     * @memberof DebidaDiligenciaComponent
     */
    public onChangeSizePage(size: number): void {
        this.length = size;
        this.recargarLista();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los registros
     * @param {string} $order Dirección del orden de los registros [ASC - DESC]
     * @memberof DebidaDiligenciaComponent
     */
    public onOrderBy(column: string, $order: string): void {
        this.selected = [];
        switch (column) {
            case 'ddi_codigo':
                this.columnaOrden = 'codigo';
                break;
            case 'ddi_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'fecha_vigencia_desde':
                this.columnaOrden = 'vigencia_desde';
                break;
            case 'fecha_vigencia_hasta':
                this.columnaOrden = 'vigencia_hasta';
                break;
            case 'fecha_creacion':
            case 'fecha_modificacion':
            case 'estado':
                this.columnaOrden = column;
                break;
            default:
                this.columnaOrden = 'fecha_modificacion'
                break;
        }
        this.start = 0;
        this.ordenDireccion = $order;
        this.recargarLista();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     *
     * @param {string} opcion Opción seleccionada
     * @param {object[]} selected Registros seleccionadosd
     * @memberof DebidaDiligenciaComponent
     */
    public onOptionMultipleSelected(opcion: string, selected: object[]): void {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     *
     * @param {InterfaceDebidaDiligencia} item Información del item
     * @memberof DebidaDiligenciaComponent
     */
    public onViewItem(item: InterfaceDebidaDiligencia): void {
        this.openmodalDebidaDiligencia('view', item.ddi_id, item.ddi_codigo, item.ddi_descripcion, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {InterfaceDebidaDiligencia} item Información del item
     * @memberof DebidaDiligenciaComponent
     */
    public onRequestDeleteItem(item: InterfaceDebidaDiligencia): void {}

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {InterfaceDebidaDiligencia} item Información del item
     * @memberof DebidaDiligenciaComponent
     */
    public onEditItem(item: InterfaceDebidaDiligencia): void {
        this.openmodalDebidaDiligencia('edit', item.ddi_id, item.ddi_codigo, item.ddi_descripcion, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    /**
     * Aplica solamente a OFEs pero debe implementarse debido a la interface.
     *
     * @param {InterfaceDebidaDiligencia} item Información del item
     * @memberof DebidaDiligenciaComponent
     */
    public onRgEstandarItem(item: InterfaceDebidaDiligencia): void {}
}

