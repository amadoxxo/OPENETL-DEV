import {Component, Input, OnInit, ViewChild} from '@angular/core';
import {BsdConstants} from '../../core/bsd_constants';
import {DatatableComponent} from '@swimlane/ngx-datatable';
import {Auth} from '../../../services/auth/auth.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from './tracking-interface';
import {MatDialog, MatDialogConfig} from "@angular/material/dialog";
import {ModalUsuariosPortalesComponent} from '../modal-usuarios-portales/modal-usuarios-portales.component';
import {GridInterface} from '../../core/grid_Interface';

@Component({
    selector: 'app-open-tracking',
    templateUrl: './open-tracking.component.html',
    styleUrls: ['./open-tracking.component.scss']
})
export class OpenTrackingComponent implements OnInit, GridInterface {

    @ViewChild('tracking', {static: true}) tracking: DatatableComponent;
    @ViewChild('selectAcciones') selectAccionesBloque;

    @Input() columns: TrackingColumnInterface [] = [];
    @Input() rows: any [] = [];
    @Input() trackingInterface: TrackingInterface = null;
    @Input() trackingOpciones: TrackingOptionsInterface = null;
    @Input() accionesLote: any [] = [];
    @Input() multiSelect: boolean = true;
    @Input() loadingIndicator: boolean = false;
    @Input() totalElements: number;
    @Input() totalShow = BsdConstants.INIT_SIZE_SEARCH;
    @Input() permisoEditar;
    @Input() permisoVer;
    @Input() permisoConfigurarDocumentoElectronico;
    @Input() permisoCambiarEstado;
    @Input() permisoAccionesBloque: boolean = true;
    @Input() indicadorOFE: boolean = false;
    @Input() trackingOrigen: any = null;
    @Input() dataExterna: boolean = true;
    @Input() mostrarBotonAperturarPeriodo: boolean = false;
    @Input() mostrarIconoTresPuntos?: boolean = false;
    @Input() paginacionCincoRegistros?: boolean = false;
    @Input() widthOpciones?: number = 150;

    public selected = [];
    public allRowsSelected: any[];
    public draw: number;
    public start: number;
    public length = BsdConstants.INIT_SIZE_SEARCH;
    public buscar: any;
    public filtroCompanias: any = [];
    public columnaOrden: string;
    public ordenDireccion: string;
    public reorderable: boolean;
    public paginationSize: any;
    public maxDate = new Date();
    public page = 0;
    public blockAll: boolean;
    public aclsUsuario: any;

    private modalUsuariosPortales: any;

    /**
     * Mensajes para la tabla principal de los listados
     */
    public messageDT = {
        emptyMessage: 'No hay data para mostrar',
        totalMessage: 'total',
        selectedMessage: 'seleccionados'
    };

    /**
     * Constructor
     */
    constructor(
        public _auth: Auth,
        private modal: MatDialog
    ) {
        this.paginationSize = [
            {label: '10',    value: 10},
            {label: '25',    value: 25},
            {label: '50',    value: 50},
            {label: '100',   value: 100},
            {label: 'TODOS', value: -1}
        ];
        this.aclsUsuario = this._auth.getAcls();
    }

    ngOnInit(): void {
        if (this.paginacionCincoRegistros) {
            this.paginationSize.unshift({label: '5', value: 5})
        }
    }

    /**
     * Permite controlar la paginación
     * @param page
     */
    public onPage(page) {
        if (this.trackingInterface) {
            this.trackingInterface.onPage(page);
        }
    }

    /**
     * Permite controlar el ordenamiento por una columna
     * @param $evt
     */
    public onSort($evt) {
        if (this.trackingInterface) {
            let column = $evt.column.prop;
            this.columnaOrden = column;
            this.ordenDireccion = $evt.newValue.toUpperCase();
            this.trackingInterface.onOrderBy(this.columnaOrden, this.ordenDireccion);
        }
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

    /**
     * Evento base para la gestion del evento activate de cada row de una grid.
     * 
     * @param $evt
     */
    public onActivate($evt) {
    }

    /**
     * Busqueda rapida
     */
    searchinline() {
        if (this.trackingInterface) {
            this.tracking.offset = 0;
            this.trackingInterface.onSearchInline(this.buscar);
        }
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
     * 
     * @param size
     */
    paginar(size) {
        if (this.trackingInterface) {
            this.length = size;
            this.trackingInterface.onChangeSizePage(this.length);
        }
    }

    /**
     * Evento para ver un item.
     * 
     * @param item
     */
    ver(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onViewItem(item);
    }

    /**
     * Evento para editar un item.
     * @param item
     */
    editar(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onEditItem(item);
    }

    /**
     * Evento para cambiar el estado un ítem.
     * 
     * @param item
     */
    cambiarEstado(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onCambiarEstadoItem(item);
    }

    /**
     * Evento para descargar un item.
     * @param item
     */
    descargar(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onDownloadItem(item);
    }

    /**
     * Evento para administrar la configuración del documento electrónico de un OFE.
     * 
     * @param item
     */
    configuracionDocumentoElectronico(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onConfigurarDocumentoElectronico(item);
    }

    /**
     * Evento para ver los usuarios asociados a un grupo de trabajo.
     * 
     * @param item
     */
    verUsuariosAsociados(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onViewUsuariosAsociados(item);
    }

    /**
     * Evento para ver los proveedores asociados a un grupo de trabajo.
     * 
     * @param item
     */
    verProveedoresAsociados(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onViewProveedoresAsociados(item);
    }

    /**
     * Evento para administrar la configuracion de servicios de un OFE.
     * 
     * @param item
     */
    configuracionServicios(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onConfigurarServicios(item);
    }

    /**
     * Evento para administrar la configuracion del documento soporte de un OFE.
     * 
     * @param item
     */
    configuracionDocumentoSoporte(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onConfigurarDocumentoSoporte(item);
    }

    /**
     * Evento para administrar los valores por defecto en documentos de un OFE.
     * 
     * @param item
     */
    valoresPorDefectoEnDocumento(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onValoresPorDefectoEnDocumento(item);
    }

    /**
     * Evento eliminar un item.
     * 
     * @param item
     */
    eliminar(item: any) {
        if (this.trackingInterface)
            this.trackingInterface.onRequestDeleteItem(item);
    }

    /**
     * Evento aperturar periodo de control de consecutivos.
     * 
     * @param item
     */
    aperturarPeriodoControlConsecutivos() {
        if (this.trackingOrigen === 'control-consecutivos' && this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebControlConsecutivosNuevo'))
            this.trackingInterface.onAperturarPeriodoControlConsecutivos();
    }

    /**
     * Evento para el combo de Acciones en Bloque
     * @param item
     */
    accionesBloque(item: any) {
        if (this.trackingInterface){
            let copy = Object.assign([], this.selected);
            this.trackingInterface.onOptionMultipleSelected(item, copy);
            this.tracking.selected = [];
            this.selected.length = 0;
            this.selectAccionesBloque.value = 'Acciones en Bloque';
        }
    }

    /**
     * Indica si se debe mostrar o no la columna de opciones
     */
    showOpciones(): boolean {
        if (!this.trackingOpciones) return  false;
        return this.trackingOpciones.showButton || this.trackingOpciones.deleteButton || this.trackingOpciones.editButton || this.trackingOpciones.downloadButton;
    }

    /**
     * Indica si se debe mostrar o no el icono de los tres puntos.
     *
     * @return {*}  {boolean}
     * @memberof OpenTrackingComponent
     */
    showIconoTresPuntos(): boolean {
        if (!this.trackingOpciones) 
            return false;

        if (
            (this.trackingOpciones.showButton && (this._auth.existePermiso(this.aclsUsuario.permisos, this.permisoVer))) ||
            (this.trackingOpciones.editButton && (this._auth.existePermiso(this.aclsUsuario.permisos, this.permisoEditar))) || 
            (this.trackingOpciones.cambiarEstadoButton && (this._auth.existePermiso(this.aclsUsuario.permisos, this.permisoCambiarEstado))) ||
            (this.trackingOpciones.configuracionDocumentoElectronicoButton && (this._auth.existePermiso(this.aclsUsuario.permisos, this.permisoConfigurarDocumentoElectronico))) ||
            (this.trackingOpciones.configuracionServicioButton && (this._auth.existePermiso(this.aclsUsuario.permisos, 'ConfigurarServicios'))) ||
            (this.trackingOpciones.configuracionDocumentoSoporteButton && (this._auth.existePermiso(this.aclsUsuario.permisos, 'ConfigurarDocumentoSoporte'))) ||
            (this.trackingOpciones.valoresPorDefectoEnDocumentoButton && (this._auth.existePermiso(this.aclsUsuario.permisos, 'ValoresDefectoDocumento'))) ||
            (this.trackingOpciones.verUsuarioAsociadoButton && (this._auth.existePermiso(this.aclsUsuario.permisos, 'ConfiguracionGrupoTrabajoAsociarUsuarioVerUsuariosAsociados'))) ||
            (this.trackingOpciones.verProveedorAsociadoButton && (this._auth.existePermiso(this.aclsUsuario.permisos, 'ConfiguracionGrupoTrabajoAsociarProveedorVerProveedoresAsociados')))
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * @param row
     * @param access
     */
    public getValue(row, access) {
        return row[access];
    }

    /**
     * Apertura una ventana modal administrar los usuarios con acceso a Portales.
     *
     * @param data Objeto con la información del registro
     */
    public openModalUsuariosPortales(data): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '800px';
        modalConfig.data = {
            parent: this,
            registro: data,
            trackingOrigen: this.trackingOrigen
        };
        this.modalUsuariosPortales = this.modal.open(ModalUsuariosPortalesComponent, modalConfig);
    }

    tieneUsuariosPortales(data) {
        if(data.usuarios_portales !== null && data.usuarios_portales !== undefined && data.usuarios_portales.length > 0)
            return true;
        else
            return false;
    }

    /**
     * Valida existen los permisos para los reportes en background de emisión, recepción o nómina electrónica.
     *
     * @return boolean
     * @memberof OpenTrackingComponent
     */
    existePermisoReporteBackground(): boolean {
        return (
            this._auth.existePermiso(this.aclsUsuario.permisos, 'EmisionReporteBackground')   ||
            this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionReporteBackground') ||
            this._auth.existePermiso(this.aclsUsuario.permisos, 'NominaReporteBackground')
        ) && this.trackingOpciones.unableDownloadButton;
    }
}
