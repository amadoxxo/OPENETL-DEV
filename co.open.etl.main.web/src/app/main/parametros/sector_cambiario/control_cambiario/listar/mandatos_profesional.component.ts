import {Component} from '@angular/core';
import {BaseComponentList} from '../../../../core/base_component_list';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../../services/auth/auth.service';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {MandatoProfesionalGestionarComponent} from '../gestionar/mandatos_profesional_gestionar.component';
import {ParametrosService} from '../../../../../services/parametros/parametros.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'listar-mandatos-profesional',
    templateUrl: './mandatos_profesional.component.html',
    styleUrls: ['./mandatos_profesional.component.scss']
})

export class MandatosProfesionalComponent extends BaseComponentList implements TrackingInterface {
    
    public estadoActual: any;
    public loadingIndicator: any;
    private modalMandatosProfesional: any;
    public aclsUsuario: any;

    public trackingInterface: TrackingInterface;

    public registros: any [] = [];

    public columns: TrackingColumnInterface[] = [
        {name: 'Código', prop: 'cmp_codigo', sorteable: true, width: 100},
        {name: 'Significado', prop: 'cmp_significado', sorteable: true},
        {name: 'Descripción', prop: 'cmp_descripcion', sorteable: true},
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
        this._parametrosService.setSlug = "cambiario-mandatos-profesional";
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
        this.loadMandatosProfesional();
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
    public loadMandatosProfesional(): void {
        this.loading(true);
        this._parametrosService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'cmp_id': reg.cmp_id,
                            'cmp_codigo': reg.cmp_codigo,
                            'cmp_significado': reg.cmp_significado,
                            'cmp_descripcion': reg.cmp_descripcion,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Mandato Profesional Cambios', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
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
        this.loadMandatosProfesional();
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
    public openmodalMandatosProfesional(action: string, cmp_id = null, cmp_codigo = null, cmp_significado = null, cmp_descripcion = null, fecha_desde = null, fecha_hasta = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            cmp_id: cmp_id,
            cmp_codigo: cmp_codigo,
            cmp_significado: cmp_significado,
            cmp_descripcion: cmp_descripcion,
            fecha_vigencia_desde: fecha_desde,
            fecha_vigencia_hasta: fecha_hasta
        };
        this.modalMandatosProfesional = this.modal.open(MandatoProfesionalGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un registro.
     * 
     */
    public closemodalMandatosProfesional(): void {
        if (this.modalMandatosProfesional) {
            this.modalMandatosProfesional.close();
            this.modalMandatosProfesional = null;
        }
    }

     /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     * 
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Mandato Profesional Cambios para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let arrCodigos = [];
                this.selected.forEach(reg => {
                    let ObjCodigos = new Object;
                    ObjCodigos['cmp_codigo'] = reg.cmp_codigo;
                    ObjCodigos['fecha_vigencia_desde'] = reg.fecha_vigencia_desde;
                    ObjCodigos['fecha_vigencia_hasta'] = reg.fecha_vigencia_hasta;

                    arrCodigos.push(ObjCodigos);
                });
                swal({
                    html: '¿Está seguro de cambiar el estado de los Mandatos Profseional Cambios seleccionados?',
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
                                this.loadMandatosProfesional();
                                this.loading(false);
                                this.showSuccess('<h3>Los Mandatos Profesionales han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de Mandatos Profesional Cambios', 'OK', 'btn btn-danger');
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
     * @memberof MandatosProfesionalComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'cmp_significado':
                this.columnaOrden = 'observacion';
                break;
            case 'cmp_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'cmp_codigo':
                this.columnaOrden = 'codigo';
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
        this.openmodalMandatosProfesional('view', item.cmp_id, item.cmp_codigo, item.cmp_significado, item.cmp_descripcion, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
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
        this.openmodalMandatosProfesional('edit', item.cmp_id, item.cmp_codigo, item.cmp_significado, item.cmp_descripcion, item.fecha_vigencia_desde, item.fecha_vigencia_hasta);
    }

    // Aplica solamente a OFEs pero debe implementarse debido a la interface
    onRgEstandarItem(item: any) {
        
    }
}

