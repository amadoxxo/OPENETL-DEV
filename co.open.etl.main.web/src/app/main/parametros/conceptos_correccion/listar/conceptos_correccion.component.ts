import {Component} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../services/auth/auth.service';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {ConceptosCorreccionGestionarComponent} from '../gestionar/conceptos_correccion_gestionar.component';
import {ParametrosService} from '../../../../services/parametros/parametros.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'listar-conceptos-correccion',
    templateUrl: './conceptos_correccion.component.html',
    styleUrls: ['./conceptos_correccion.component.scss']
})

export class ConceptosCorreccionComponent extends BaseComponentList implements TrackingInterface {
    
    public estadoActual: any;
    public loadingIndicator: any;
    private modalConceptoCorreccion: any;
    public aclsUsuario: any;

    public trackingInterface: TrackingInterface;

    public registros: any [] = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Código', prop: 'cco_codigo', sorteable: true, width: 100},
        {name: 'Tipo', prop: 'cco_tipo', sorteable: true, width: 100},
        {name: 'Descripción', prop: 'cco_descripcion', sorteable: true},
        {name: 'Vigencia Desde', prop: 'fecha_vigencia_desde', sorteable: true, width: 150},
        {name: 'Vigencia Hasta', prop: 'fecha_vigencia_hasta', sorteable: true, width: 150},
        {name: 'Estado', prop: 'estado', sorteable: true, width: 100}
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
     * @param modal
     * @param _parametrosService
     */
    constructor(private _router: Router,
                public _auth: Auth,
                private modal: MatDialog,
                private _parametrosService: ParametrosService) {
        super();
        this._parametrosService.setSlug = "concepto-correccion";
        this.trackingInterface = this;
        this.rows = [];
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     * 
     */
    private init() {
        this.initDataSort('codigo');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadConceptosCorreccion();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     * 
     */
    getSearchParametersInline(excel = false, tracking = true): string {
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
     * Se encarga de traer la data de los diferentes registros.
     * 
     */
    public loadConceptosCorreccion(): void {
        this.loading(true);
        this._parametrosService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'cco_id': reg.cco_id,
                            'cco_codigo': reg.cco_codigo,
                            'cco_tipo': reg.cco_tipo,
                            'cco_descripcion': reg.cco_descripcion,
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
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Conceptos de Corrección', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
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
        this.loadConceptosCorreccion();
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
     * Apertura una ventana modal para crear o editar un registro.
     * 
     * @param usuario
     */
    public openModalConceptoCorreccion(action: string, cco_id = null, cco_codigo = null, cco_tipo = null, fecha_desde = null, fecha_hasta = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            cco_id: cco_id,
            cco_codigo: cco_codigo,
            cco_tipo: cco_tipo,
            fecha_vigencia_desde: fecha_desde,
            fecha_vigencia_hasta: fecha_hasta
        };
        this.modalConceptoCorreccion = this.modal.open(ConceptosCorreccionGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un registro.
     * 
     */
    public closeModalConceptoCorreccion(): void {
        if (this.modalConceptoCorreccion) {
            this.modalConceptoCorreccion.close();
            this.modalConceptoCorreccion = null;
        }
    }

     /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     * 
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Concepto de Corrección para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let arrCodigos = [];
                this.selected.forEach(reg=>{​​​​​
                    let ObjCodigos = new Object;
                    ObjCodigos['cco_tipo'] = reg.cco_tipo;
                    ObjCodigos['cco_codigo'] = reg.cco_codigo;
                    ObjCodigos['fecha_vigencia_desde'] = reg.fecha_vigencia_desde;
                    ObjCodigos['fecha_vigencia_hasta'] = reg.fecha_vigencia_hasta;
                    arrCodigos.push(ObjCodigos);
                }​​​​​);
                swal({
                    html: '¿Está seguro de cambiar el estado de los Conceptos de Corrección seleccionados?',
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
                        this._parametrosService.cambiarEstadoCodigos(arrCodigos).subscribe(
                            response => {
                                this.loadConceptosCorreccion();
                                this.loading(false);
                                this.showSuccess('<h3>Los Conceptos de Corrección han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
        this._parametrosService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de Conceptos de Corrección', 'OK', 'btn btn-danger');
            }
        );
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
     * @param {string} column Columna por la cual se organizan los registros
     * @param {string} $order Dirección del orden de los registros [ASC - DESC]
     * @memberof ConceptosCorreccionComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'cco_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'cco_codigo':
                this.columnaOrden = 'codigo';
                break;
            case 'cco_tipo':
                this.columnaOrden = 'tipo';
                break;
            case 'fecha_vigencia_desde':
                this.columnaOrden = 'vigencia_desde';
                break;
            case 'fecha_vigencia_hasta':
                this.columnaOrden = 'vigencia_hasta';
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
        this.openModalConceptoCorreccion('view', item.cco_id, item.cco_codigo, item.cco_tipo, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
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
        this.openModalConceptoCorreccion('edit', item.cco_id, item.cco_codigo, item.cco_tipo, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    // Aplica solamente a OFEs pero debe implementarse debido a la interface
    onConfigurarDocumentoElectronico(item: any) {
        
    }
}

