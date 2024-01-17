import swal from 'sweetalert2';
import { Auth } from '../../../services/auth/auth.service';
import { BsdConstants } from '../../core/bsd_constants';
import { GridInterface } from '../../core/grid_Interface';
import { JwtHelperService } from '@auth0/angular-jwt';
import { NgSelectComponent } from '@ng-select/ng-select';
import { BaseComponentList } from '../../core/base_component_list';
import { DatatableComponent } from '@swimlane/ngx-datatable';
import { Component, Input, OnInit, ViewChild } from '@angular/core';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';
import { GestionDocumentosService } from '../../../services/proyectos-especiales/recepcion/emssanar/gestion-documentos.service';
import { GestionDocumentosTrackingColumnInterface, GestionDocumentosTrackingInterface } from './gestion-documentos-tracking-interface';
import { ModalVerDetalleComponent } from '../../proyectos-especiales/recepcion/emssanar/gestion-documentos/modals/modal-ver-detalle/modal-ver-detalle.component';

/**
 * Componente desarrollado para ser utilizado exclusivamente en los trackings de las etapas de Gestión de Documentos.
 */
@Component({
    selector: 'app-gestion-documentos-tracking',
    templateUrl: './gestion-documentos-tracking.component.html',
    styleUrls: ['./gestion-documentos-tracking.component.scss']
})
export class GestionDocumentosTrackingComponent extends BaseComponentList implements OnInit, GridInterface {
    @ViewChild('tracking', {static: true}) tracking: DatatableComponent;
    @ViewChild('selectAcciones') ngSelectComponent: NgSelectComponent;

    @Input() trackingInterface     : GestionDocumentosTrackingInterface;
    @Input() columns               : GestionDocumentosTrackingColumnInterface [] = [];
    @Input() rows                  : any [] = [];
    @Input() accionesLote          : any [] = [];
    @Input() multiSelect           : boolean = true;
    @Input() loadingIndicator      : boolean = false;
    @Input() totalElements         : number;
    @Input() existeConsulta        : boolean;
    @Input() linkAnterior          : string;
    @Input() linkSiguiente         : string;
    @Input() totalShow             = BsdConstants.INIT_SIZE_SEARCH;
    @Input() etapa                 : number;
    @Input() descripcionEtapa      : string;
    @Input() permisoGestionarEtapa : string = '';
    @Input() permisoSiguienteEtapa : string = '';

    public usuario               : any;
    public selected              = [];
    public allRowsSelected       : any[];
    public length                = BsdConstants.INIT_SIZE_SEARCH;
    public buscar                : any;
    public columnaOrden          : string;
    public ordenDireccion        : string;
    public reorderable           : boolean;
    public paginationSize        : any;
    public maxDate               = new Date();
    public page                  = 0;
    public aclsUsuario           : any;
    public selectedOption        : any;
    public tamanoArchivoSuperior : any;
    public descripcionAprobado   : string = '';
    public descripcionNoAprobado : string = '';

    /**
     * Mensajes para la tabla principal de los listados
     */
    public messageDT = {
        emptyMessage   : 'No hay data para mostrar',
        totalMessage   : 'total',
        selectedMessage: 'seleccionados'
    };

    /**
     * Crea una instancia de GestionDocumentosTrackingComponent.
     * 
     * @param {Auth} _auth
     * @param {JwtHelperService} jwtHelperService
     * @param {GestionDocumentosService} _gestionDocumentosService
     * @param {MatDialog} _matDialog
     * @memberof GestionDocumentosTrackingComponent
     */
    constructor(
        public _auth             : Auth,
        private _matDialog       : MatDialog,
        private jwtHelperService : JwtHelperService,
        private _gestionDocumentosService: GestionDocumentosService
    ) {
        super();
        this.paginationSize = [
            {label: '10',    value: 10},
            {label: '25',    value: 25},
            {label: '50',    value: 50},
            {label: '100',   value: 100}
        ];
    }

    /**
     * Ciclo OnInit del componente.
     *
     * @memberof GestionDocumentosTrackingComponent
     */
    ngOnInit(): void {
        this.usuario     = this.jwtHelperService.decodeToken();
        this.aclsUsuario = this._auth.getAcls();

        switch (this.etapa) {
            case 1:
                this.descripcionAprobado   = 'Conforme';
                this.descripcionNoAprobado = 'No Conforme';
                break;
            case 2:
                this.descripcionAprobado   = 'Revisión Conforme';
                this.descripcionNoAprobado = 'Revisión No Conforme';
                break;
            case 3:
                this.descripcionAprobado   = 'Aprobación Conforme';
                this.descripcionNoAprobado = 'Aprobación No Conforme';
                break;
            case 4:
                this.descripcionAprobado   = 'Aprobada por Contabilidad';
                this.descripcionNoAprobado = 'No Aprobada por Contabilidad';
                break;
            case 5:
                this.descripcionAprobado   = 'Aprobada por Impuestos';
                this.descripcionNoAprobado = 'No Aprobada por Impuestos';
                break;
            case 6:
                this.descripcionAprobado   = 'Aprobada y Pagada';
                this.descripcionNoAprobado = 'No Aprobada para Pago';
                break;
            case 7:
                this.descripcionAprobado   = 'Aprobada y Pagada';
                break;
            default:
                break;
        }
    }

    /**
     * Permite realizar la paginación.
     * 
     * @param {string} page Número de página
     * @memberof GestionDocumentosTrackingComponent
     */
    public onPage(page: string): void {
        if (page && this.trackingInterface)
            this.trackingInterface.onPage(page);
    }

    /**
     * Permite realizar el ordenamiento por una columna.
     * 
     * @param $evt Evento de ordenamiento
     * @memberof GestionDocumentosTrackingComponent
     */
    public onSort($evt): void {
        if (this.trackingInterface) {
            let column          = $evt.column.prop;
            this.columnaOrden   = column;
            this.ordenDireccion = $evt.newValue.toUpperCase();
            this.trackingInterface.onOrderBy(this.columnaOrden, this.ordenDireccion);
        }
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     * 
     * @param selected Registros seleccionados
     * @memberof GestionDocumentosTrackingComponent
     */
    onSelect({selected}): void {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
     *
     * @param {number} size Cantidad de registros a mostrar
     * @memberof GestionDocumentosTrackingComponent
     */
    paginar(size: number): void {
        if (this.trackingInterface) {
            this.length = size;
            this.trackingInterface.onChangeSizePage(this.length);
        }
    }

    /**
     * Evento para descargar el excel de la grid.
     *
     * @memberof GestionDocumentosTrackingComponent
     */
    async descargarExcel(): Promise<void> {
        if (this.trackingInterface) {
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
                    this.trackingInterface.onAgendarReporteBackground?.();
                } else {
                    this.trackingInterface.onDescargarExcel();
                }
            }).catch(swal.noop);
        }
    }

    /**
     * Ejecuta el evento de la opción Gestionar Fe/Ds.
     *
     * @param {object} row Información del registro
     * @memberof GestionDocumentosTrackingComponent
     */
    onGestionarFeDs(row: object): void {
        if (this.trackingInterface) {
            this.trackingInterface.onValidarGestionarFeDs([row]);
        }
    }

    /**
     * Ejecuta el evento de la opción Siguiente Etapa.
     *
     * @param {object} row Información del registro
     * @memberof GestionDocumentosTrackingComponent
     */
    onSiguienteEtapa(row: object): void {
        if (this.trackingInterface) {
            this.trackingInterface.onValidarSiguienteEtapa([row]);
        }
    }

    /**
     * Ejecuta el evento de la opción Gestionar Fe/Ds.
     *
     * @param {object} row Información del registro
     * @memberof GestionDocumentosTrackingComponent
     */
    onAsignarCentroOperacion(row: object): void {
        if (this.trackingInterface) {
            this.trackingInterface.onValidarAsignarCentroOperacion([row]);
        }
    }

    /**
     * Ejecuta el evento de la opción Gestionar Fe/Ds.
     *
     * @param {object} row Información del registro
     * @memberof GestionDocumentosTrackingComponent
     */
    onAsignarCentroCosto(row: object): void {
        if (this.trackingInterface) {
            this.trackingInterface.onValidarAsignarCentroCosto([row]);
        }
    }

    /**
     * Ejecuta el evento de la opción Datos Contabilizado.
     *
     * @param {object} row Información del registro
     * @memberof GestionDocumentosTrackingComponent
     */
    onAsignarDatosContabilizado(row: object): void {
        if (this.trackingInterface) {
            this.trackingInterface.onValidarDatosContabilizado([row]);
        }
    }

    /**
     * Evento para el combo de Acciones en Bloque.
     *
     * @param {*} evt Evento ejecutado
     * @param {*} [row=undefined] Información de la fila
     * @memberof GestionDocumentosTrackingComponent
     */
    accionesBloque(evt, row: any = undefined): void {
        if (evt && this.trackingInterface && !row){
            let copy = Object.assign([], this.selected);
            this.trackingInterface.onOptionMultipleSelected(this.selectedOption, copy);
            this.tracking.selected = [];
            this.selected.length   = 0;
            this.selectedOption    = null;
            this.ngSelectComponent.handleClearClick();
        } else if(row) {
            this.trackingInterface.onOptionMultipleSelected(evt.id, [row]);
            this.tracking.selected = [];
            this.selected.length   = 0;
            this.selectedOption    = null;
            this.ngSelectComponent.handleClearClick();
        }
    }

    /**
     * Obtiene el valor de una columna.
     *
     * @param  {object} row Información de la fila
     * @param  {string} access Nombre de la columna
     * @return {string}
     * @memberof GestionDocumentosTrackingComponent
     */
    public getValue(row: object, access: string): string {
        return row[access];
    }

    /**
     * Actualiza el contenido del array de acciones en bloque.
     *
     * @param {object[]} accionesBloque Array de acciones en bloque
     * @memberof GestionDocumentosTrackingComponent
     */
    actualizarAccionesLote(accionesBloque: object[]): void {
        this.accionesLote = accionesBloque
        this.accionesLote = [...this.accionesLote];
    }

    /**
     * Permite validar si el documento tiene estado Sin Gestión.
     *
     * @param {estado_gestion} estado_gestion Estado de gestión del registro
     * @return {boolean}
     * @memberof GestionDocumentosTrackingComponent
     */
    checkEstadoSinGestion({ estado_gestion }): boolean {
        if (estado_gestion && estado_gestion === 'SIN_GESTION') {
            return true
        }
        return false;
    }

    /**
     * Permite validar si el documento tiene estado de Aprobado para pasar a la siguiente etapa.
     *
     * @param {estado_gestion} estado_gestion Estado de gestión del registro
     * @return {boolean}
     * @memberof GestionDocumentosTrackingComponent
     */
    checkEstadoAprobado({ estado_gestion }): boolean {
        if (estado_gestion && ['CONFORME', 'REVISION_CONFORME', 'APROBACION_CONFORME', 'APROBADA_POR_CONTABILIDAD', 'APROBADA_POR_IMPUESTOS', 'APROBADA_Y_PAGADA'].includes(estado_gestion)) {
            return true
        }
        return false;
    }

    /**
     * Permite validar si el documento tiene estado de No Aprobado para pasar a la siguiente etapa.
     *
     * @param {estado_gestion} estado_gestion Estado de gestión del registro
     * @return {boolean}
     * @memberof GestionDocumentosTrackingComponent
     */
    checkEstadoNoAprobado({ estado_gestion }): boolean {
        if (estado_gestion && ['NO_CONFORME', 'REVISION_NO_CONFORME', 'APROBACION_NO_CONFORME', 'NO_APROBADA_POR_CONTABILIDAD', 'NO_APROBADA_POR_IMPUESTOS', 'NO_APROBADA_PARA_PAGO'].includes(estado_gestion)) {
            return true
        }
        return false;
    }

    /**
     * Permite validar si el documento tiene estado de No Aprobado para pasar a la siguiente etapa.
     *
     * @param {estado_gestion} estado_gestion Estado de gestión del registro
     * @return {boolean}
     * @memberof GestionDocumentosTrackingComponent
     */
    checkEstadoRechazado({ estado_gestion }): boolean {
        if (estado_gestion && estado_gestion === 'RECHAZADO') {
            return true
        }
        return false;
    }

    /**
     *  Apertura una ventana modal para ver la información de cada etapa.
     *
     * @param {*} data Información del registro
     * @memberof GestionDocumentosTrackingComponent
     */
    public openModalVerDetalle(data: any): void {
        this.loading(true);
        this._gestionDocumentosService.verDetalleEtapas(data.gdo_id).subscribe({
            next: response => {
                this.loading(false);
                const modalConfig = new MatDialogConfig();
                modalConfig.autoFocus = true;
                modalConfig.width = '600px';
                modalConfig.data = {
                    etapa             : this.etapa,
                    informacionEtapas : response.data,
                    parent            : this,
                };
                this._matDialog.open(ModalVerDetalleComponent, modalConfig);
            },
            error: error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al consultar el detalle de las etapas');
            }
        });
    }

}
