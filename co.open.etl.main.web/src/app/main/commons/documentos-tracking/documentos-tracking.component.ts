import { Component, Input, Output, OnInit, ViewChild, EventEmitter } from '@angular/core';
import { BaseComponentList } from '../../core/base_component_list';
import { BsdConstants } from '../../core/bsd_constants';
import { DatatableComponent } from '@swimlane/ngx-datatable';
import { Auth } from '../../../services/auth/auth.service';
import { NgSelectComponent } from '@ng-select/ng-select';
import { DocumentosTrackingColumnInterface, DocumentosTrackingInterface } from './documentos-tracking-interface';
import { MatDialog , MatDialogConfig} from "@angular/material/dialog";
import { ModalResumenEstadosDocumentoComponent } from "../modal-resumen-estados-documento/modal-resumen-estados-documento.component";
import { ModalNotificacionDocumentoComponent } from "../modal-notificacion-documento/modal-notificacion-documento.component";
import { ModalDocumentosAnexosComponent } from '../../modals/modal-documentos-anexos/modal-documentos-anexos.component';
import { Router } from '@angular/router';
import swal from 'sweetalert2';
import { JwtHelperService } from '@auth0/angular-jwt';
import { OpenEcmService } from '../../../services/ecm/openecm.service';
import { DocumentosService } from '../../../services/emision/documentos.service';
import { DocumentosRecibidosService } from '../../../services/recepcion/documentos_recibidos.service';
import { RadianService } from '../../../services/radian/radian.service';
import { NominaElectronicaService } from '../../../services/nomina-electronica/nomina_electronica.service';
import { ModalReemplazarPdfComponent } from '../modal-reemplazar-pdf/modal-reemplazar-pdf.component';
import { ModalCorreosRecibidosComponent } from '../modal-correos-recibidos/modal-correos-recibidos.component';
import { CorreosRecibidosService } from '../../../services/recepcion/correos_recibidos.service';
import {GridInterface} from '../../core/grid_Interface';

@Component({
    selector: 'app-documentos-tracking',
    templateUrl: './documentos-tracking.component.html',
    styleUrls: ['./documentos-tracking.component.scss']
})
export class DocumentosTrackingComponent extends BaseComponentList implements OnInit, GridInterface {

    // Usuario en linea
    public usuario: any;

    @ViewChild('tracking', {static: true}) tracking: DatatableComponent;
    @ViewChild('selectAcciones') ngSelectComponent: NgSelectComponent;

    @Input() columns: DocumentosTrackingColumnInterface [] = [];
    @Input() rows: any [] = [];
    @Input() trackingInterface: DocumentosTrackingInterface = null;
    @Input() accionesLote: any [] = [];
    @Input() arrDescargas: any [] = [];
    @Input() arrEnvioCorreo: any [] = [];
    @Input() arrReenvioNotificacion: any [] = [];
    @Input() multiSelect: boolean = true;
    @Input() loadingIndicator: boolean = false;
    @Input() totalElements: number;
    @Input() existeConsulta: boolean;
    @Input() tipo: string;
    @Input() visorEcm: boolean = false;
    @Input() totalShow = BsdConstants.INIT_SIZE_SEARCH;
    @Output() recargarLista = new EventEmitter<any>();

    public botonReenvioEmail: boolean = true;
    public tiposDescargas: any;
    public tiposEnvioCorreo: any; // --> Eliminar
    public tiposReenvioNotificacion: any;
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
    public selectedOption: any;
    public tamanoArchivoSuperior: any;
    public tipoDocumentosSoporteEnviados: boolean = false;
    public tipoDocumentosSoporteSinEnvio: boolean = false;

    private modalEstados: any;
    private modalNotificacion: any;
    private modalDocumentosAnexos: any;
    private modalReemplazarPdf: any;
    private modalCorreoRecibido: any;

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
        public _auth                       : Auth,
        private _router                    : Router,
        private modal                      : MatDialog,
        public _openEcm                    : OpenEcmService,
        private jwtHelperService           : JwtHelperService,
        private _documentosService         : DocumentosService,
        private _nominaElectronicaService  : NominaElectronicaService,
        private _documentosRecepcionService: DocumentosRecibidosService,
        private _correosRecibidosService   : CorreosRecibidosService,
        private _radianService             : RadianService
    ) {
        super();
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
        this.usuario = this.jwtHelperService.decodeToken();

        if (this._router.url == '/documento-soporte/documentos-enviados') {
            this.botonReenvioEmail = false;
            this.tipoDocumentosSoporteEnviados = true;
        } else if (this._router.url == '/documento-soporte/documentos-sin-envio') {
            this.tipoDocumentosSoporteSinEnvio = true;
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
     * Evento de selectall del checkbox primario de la grid
     * @param selected
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Evento base para la gestion del evento activate de cada row de una grid
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
     * Cambia el numero de items a mostrar y refresca la grid
     * @param size
     */
    paginar(size) {
        if (this.trackingInterface) {
            this.length = size;
            this.trackingInterface.onChangeSizePage(this.length);
        }
    }

    /**
     * Evento para descargar un documento.
     * 
     * @param item
     */
    descargar() {
        if (this.trackingInterface) {
            let copy = Object.assign([], this.selected);
            this.trackingInterface.onDescargarItems(copy, this.tiposDescargas);
            this.tracking.selected = [];
            this.selected.length = 0;
        }
    }

    /**
     * Evento reenvío de notificaciones de eventos.
     * 
     * @param item
     */
    reenvioNotificacion() {
        if (this.trackingInterface) {
            let copy = Object.assign([], this.selected);
            this.trackingInterface.onReenvioNotificacion(copy, this.tiposReenvioNotificacion);
            this.tracking.selected = [];
            this.selected.length = 0;
        }
    }

    /**
     * Evento para enviar uno o varios documentos.
     * 
     * @param item
     */
    enviar() {
        if (this.trackingInterface) {
            let copy = Object.assign([], this.selected);
            this.trackingInterface.onEnviarItems(copy, this.tiposEnvioCorreo);
            this.tracking.selected = [];
            this.selected.length = 0;
        }
    }

    /**
     * Evento para descargar el excel de la grid.
     * 
     * @param item
     */
    async descargarExcel() {
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
                    this.trackingInterface.onAgendarReporteBackground();
                } else {
                    this.trackingInterface.onDescargarExcel();
                }
            }).catch(swal.noop);
        }
    }

    /**
     * Evento para el combo de Acciones en Bloque.
     * 
     * @param item
     */
    accionesBloque(evt) {
        if (evt && this.trackingInterface){
            let copy = Object.assign([], this.selected);
            this.trackingInterface.onOptionMultipleSelected(this.selectedOption, copy);
            this.tracking.selected = [];
            this.selected.length = 0;
            // this.selectAccionesBloque.value = 'Acciones en Bloque';
            this.selectedOption = null;
            this.ngSelectComponent.handleClearClick();
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
     * Evento para manejar los click en los iconos de las opciones.
     *
     * @param item
     * @param opcion
     */
    manageOptions(item: any, opcion: string) {
        if (this.trackingInterface)
            this.trackingInterface.onOptionItem(item, opcion);
    }

    /**
     * Determina si debe mostrarse en el control para estado de DO exitoso y NO notificado.
     *
     * @param data
     */
    checkEstadoDoExitosoNoNotificado(data) {
        if (data.estado_documento && data.estado_documento === 'APROBADO') {
            return true;
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de DO exitoso y notificado.
     *
     * @param data
     */
    checkEstadoNotificacionExitoso(data) {
        if (data.estado_documento && data.estado_documento === 'APROBADO_NOTIFICACION') {
            return true;
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de DO exitoso y notificado.
     *
     * @param data
     */
    checkEstadoExitosoNotificado(data) {
        if (data.estado_notificacion && data.estado_notificacion === 'NOTIFICACION_EXITOSO') {
            this.tamanoArchivoSuperior = data.notificacion_tamano_superior;
            return true;
        }
        return false;
    }

    /**
     * Determina si la notificación de un documento fue entregada mediante el evento delivery de los eventos de notificación del documento.
     *
     * @param data
     */
    checkNotificacionEntregada(data) {
        let notificado = false;
        if (data.notificacion_tipo_evento) {
            notificado = true;
        } else {
            notificado = true && !this.tamanoArchivoSuperior;
        }

        return notificado;
    }

    /**
     * Determina si debe mostrarse el icono de notificación para los estados existentes.
     *
     * @param data Objeto con la información del registro
     */
    checkEventosNotificadoRecepcion(data) {
        if (
            data.estado_notificacion === 'NOTACUSERECIBO' || data.estado_notificacion === 'NOTRECIBOBIEN' ||
            data.estado_notificacion === 'NOTACEPTACION' || data.estado_notificacion === 'NOTRECHAZO' || 
            data.estado_notificacion_fallido === 'NOTACUSERECIBO_FALLIDO' || data.estado_notificacion_fallido === 'NOTRECIBOBIEN_FALLIDO' ||
            data.estado_notificacion_fallido === 'NOTACEPTACION_FALLIDO' || data.estado_notificacion_fallido === 'NOTRECHAZO_FALLIDO'
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determina si la notificación de un documento fue fallida o exitosa.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoNotificacion(data) {
        if (
            data.estado_notificacion_fallido === 'NOTACUSERECIBO_FALLIDO' ||
            data.estado_notificacion_fallido === 'NOTRECIBOBIEN_FALLIDO' ||
            data.estado_notificacion_fallido === 'NOTACEPTACION_FALLIDO' ||
            data.estado_notificacion_fallido === 'NOTRECHAZO_FALLIDO'
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determina si debe mostrarse en el control para estado de DO exitoso y notificado.
     *
     * @param data
     */
    checkEstadoDoFallido(data) {
        if ((data.estado_documento && data.estado_documento === 'RECHAZADO') || (data.estado_xml && data.estado_xml === 'XML_FALLIDO') || 
            (data.estado_ubl && data.estado_ubl === 'UBL_FALLIDO') || (data.estado_attacheddocument && data.estado_attacheddocument === 'ATTACHEDDOCUMENT_FALLIDO'))
        {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de DO exitoso y notificado.
     *
     * @param data
     */
    checkEstadoDoWarning(data) {
        let i = 0;
        let ublExitoso = 0;
        let ublEnProceso = 0;
        let doExitoso = 0;
        let doEnProceso = 0;

        if (this.tipo === 'nomina-enviados') {
            if (data.estado_xml == 'XML_EXITOSO')
                ublExitoso += 1;

            if (data.estado_xml == 'XML_PROCESO')
                ublEnProceso += 1;
        } else {
            if (data.estado_ubl == 'UBL_EXITOSO')
                ublExitoso += 1;

            if (data.estado_ubl == 'UBL_PROCESO')
                ublEnProceso += 1;
        }

        if (data.estado_do == 'DO_EXITOSO')
            doExitoso += 1;

        if (data.estado_do == 'DO_PROCESO')
            doEnProceso += 1;

        if (ublExitoso === 0 && doExitoso === 0) {
            if (ublEnProceso > 0 || doEnProceso > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ubica el estado RDI EXITOSO para verificar si el estado tieno o no inconsistencias.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoRdiInconsistencias(data) {
        if (data.estado_documento && data.estado_documento === 'RDI_INCONSISTENCIA') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Aceptado Dian.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoDian(data) {
        if (data.estado_documento && data.estado_documento === 'APROBADO') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Aceptado Dian con Notificación.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoDianNotificacion(data) {
        if (data.estado_documento && data.estado_documento === 'APROBADO_NOTIFICACION') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Rechazado Dian.
     *
     * @param data
     */
    checkEstadoRechazadoDian(data) {
        if (data.estado_documento && data.estado_documento === 'RECHAZADO') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de GetStatus Pendiente.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoGetStatusWarning(data) {
        if (data.estado_documento && data.estado_documento === 'GETSTATUS_ENPROCESO') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Aceptado exitoso.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptado(data) {
        if (data.estado_dian && data.estado_dian === 'ACEPTACION') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Aceptado fallido.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoFallido(data) {
        if (data.estado_dian && data.estado_dian === 'ACEPTACION_FALLIDO') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Aceptado Tácitamente exitoso.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoTacitamente(data) {
        if (data.estado_dian && data.estado_dian === 'ACEPTACIONT') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Aceptado Tácitamente fallido.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoTacitamenteFallido(data) {
        if (data.estado_dian && data.estado_dian === 'ACEPTACIONT_FALLIDO') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Rechazado exitoso.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoRechazado(data) {
        if (data.estado_dian && data.estado_dian === 'RECHAZO') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Rechazado fallido.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoRechazadoFallido(data) {
        if (data.estado_dian && data.estado_dian === 'RECHAZO_FALLIDO') {
            return true
        }
        return false;
    }

    /**
     * Determina si debe mostrarse en el control para documentos anexos.
     *
     * @param data Objeto con la información del registro
     */
    checkDocumentosAnexos(data) {
        if (data.aplica_documento_anexo && data.aplica_documento_anexo === 'SI') {
            return true
        }
        return false;
    }

    /**
     * Determina si el documento fue enviado a la DIAN pero no se encuentra estado DO ni Fallido ni Exitoso.
     *
     * @param data Objeto con la información del registro
     */
    checkEnviadoDianSinEstadoDo(data) {
        let dian = false;
        if (this.tipo == 'nomina-enviados') {
            dian = data.cdn_fecha_validacion_dian !== '' && data.cdn_fecha_validacion_dian !== null && data.cdn_fecha_validacion_dian !== undefined ? true : false;
        } else {
            dian = data.cdo_fecha_validacion_dian !== '' && data.cdo_fecha_validacion_dian !== null && data.cdo_fecha_validacion_dian !== undefined ? true : false;
        }

        let _do  = false;
        if (data.estado_do == '') {
            _do = true;
        }

        if(dian && _do)
            return true;
        else
            return false
    }

    /**
     * Determina si el OFE esta relacionado con Cadisoft y si el usuario autenticado tiene el permiso para reemplazar el PDF.
     *
     * @param {object} data Objeto con la información del registro
     */
    checkCadisoftReemplazarPdf(data) {
        if(data.ofe_cadisoft_activo && data.ofe_cadisoft_activo === 'SI' && this._auth.existePermiso(this.aclsUsuario.permisos, 'EmisionReemplazarPdf')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Muestra un mensaje de alerta.
     *
     * @memberof DocumentosTrackingComponent
     */
    mostrarMensajeInformacionNoDisponible() {
        swal({
            html: '<h2>El documento ya fue enviado a la DIAN. Información no disponible, por favor comuníquese con el administrador</h2>',
            type: 'info',
            confirmButtonClass: 'btn btn-success',
            confirmButtonText: 'Aceptar',
            buttonsStyling: false,
            allowOutsideClick: false
        }).catch(swal.noop);
    }

    /**
     * Apertura una ventana modal para ver el resumen de estados de un documento.
     *
     * @param data Objeto con la información del registro
     * @param estadoEventoAceptadoRechazado Indica si se debe mostrar la información de aceptación/rechazo del documento en el proceso de emisión y recepción
     * @param estadoTransmisionErp Indica si se debe mostrar la información de transmisión al ERP del documento en el proceso de recepción
     */
    public openModalEstados(data, estadoEventoAceptadoRechazado = undefined, estadoTransmisionErp = undefined, estadoTransmisionOpenComex = undefined): void {
        this.loading(true);

        data.estadoTransmisionErp          = estadoTransmisionErp;
        data.estadoTransmisionOpenComex    = estadoTransmisionOpenComex;
        data.estadoEventoAceptadoRechazado = estadoEventoAceptadoRechazado;
        let registro = {};
        switch (this.tipo) {
            case 'recibidos':
                data.proceso = 'recepcion';
                registro = {
                    cdo_id: data.cdo_id,
                    tracking: this.tipo
                };

                this._documentosRecepcionService.obtenerEstadosDocumento(registro).subscribe(
                    response => {
                        this.loading(false);
                        data.estados = response.data;
                        const modalConfig = new MatDialogConfig();
                        modalConfig.autoFocus = true;
                        modalConfig.width = '800px';
                        modalConfig.data = {
                            documento: data,
                            parent: this,
                        };
                        this.modalEstados = this.modal.open(ModalResumenEstadosDocumentoComponent, modalConfig);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los estados del documento');
                    }
                );
                break
            case 'nomina-enviados':
                data.proceso = 'nomina-electronica';
                registro = {
                    cdn_id: data.cdn_id
                };

                this._nominaElectronicaService.obtenerEstadosDocumento(registro).subscribe(
                    response => {
                        this.loading(false);
                        data.estados = response.data;

                        const modalConfig = new MatDialogConfig();
                        modalConfig.autoFocus = true;
                        modalConfig.width = '800px';
                        modalConfig.data = {
                            documento: data,
                            parent: this,
                        };
                        this.modalEstados = this.modal.open(ModalResumenEstadosDocumentoComponent, modalConfig);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los estados del documento');
                    }
                );
                break;
            case 'radian':
                data.proceso = 'radian';
                registro = {
                    cdo_id: data.cdo_id,
                    tracking: this.tipo
                };

                this._radianService.obtenerEstadosDocumento(registro).subscribe(
                    response => {
                        this.loading(false);
                        data.estados = response.data;

                        const modalConfig = new MatDialogConfig();
                        modalConfig.autoFocus = true;
                        modalConfig.width = '800px';
                        modalConfig.data = {
                            documento: data,
                            parent: this,
                        };
                        this.modalEstados = this.modal.open(ModalResumenEstadosDocumentoComponent, modalConfig);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los estados del documento');
                    }
                );
                break;
            default:
                data.proceso = 'emision';
                registro = {
                    cdo_id: data.cdo_id
                };
                this._documentosService.obtenerEstadosDocumento(registro).subscribe(
                    response => {
                        this.loading(false);
                        data.estados = response.data;

                        const modalConfig = new MatDialogConfig();
                        modalConfig.autoFocus = true;
                        modalConfig.width = '800px';
                        modalConfig.data = {
                            documento: data,
                            parent: this,
                        };
                        this.modalEstados = this.modal.open(ModalResumenEstadosDocumentoComponent, modalConfig);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los estados del documento');
                    }
                );
                break;
        }
    }

    /**
     * Apertura una ventana modal para ver la información de notificación de un documento.
     *
     * @param data Objeto con la información del registro
     */
    public openModalNotificacion(data): void {
        this.loading(true);
        let registro = {
            cdo_id: data.cdo_id
        };

        switch (this.tipo) {
            case 'recibidos':
                this._documentosRecepcionService.obtenerEstadosDocumento(registro).subscribe(
                    response => {
                        this.loading(false);
                        data.estados = response.data;
                        data.eventos_notificacion = response.eventos_notificacion;

                        const modalConfig = new MatDialogConfig();
                        modalConfig.autoFocus = true;
                        modalConfig.width = '800px';
                        modalConfig.data = {
                            documento: data,
                            parent: this,
                            proceso: 'recepcion'
                        };
                        this.modalEstados = this.modal.open(ModalNotificacionDocumentoComponent, modalConfig);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los estados de notificación del documento');
                    }
                );
                break;
            default:
                this._documentosService.obtenerEstadosDocumento(registro).subscribe(
                    response => {
                        this.loading(false);
                        data.estados = response.data;
                        data.eventos_notificacion = response.eventos_notificacion;
                        
                        const modalConfig = new MatDialogConfig();
                        modalConfig.autoFocus = true;
                        modalConfig.width = '800px';
                        modalConfig.data = {
                            documento: data,
                            parent: this,
                            proceso: 'emision'
                        };
                        this.modalEstados = this.modal.open(ModalNotificacionDocumentoComponent, modalConfig);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los estados de notificación del documento');
                    }
                );
                break;
        }
    }

    /**
     * Apertura una ventana modal para ver los documentos anexos de un documento.
     *
     * @param item Objeto con la información del registro
     */
    public openModalDocumentosAnexos(item: any): void {
        this.loading(true);

        let registro = {
            cdo_id: item.cdo_id
        };

        switch (this.tipo) {
            case 'recibidos':
                this._documentosRecepcionService.obtenerDocumentosAnexos(registro).subscribe(
                    response => {
                        this.loading(false);
                        item.get_documentos_anexos = response.data;
            
                        const modalConfig = new MatDialogConfig();
                        modalConfig.autoFocus = true;
                        modalConfig.width = '800px';
                        modalConfig.data = {
                            item: item,
                            parent: this,
                            proceso: 'recepcion'
                        };
                        this.modalDocumentosAnexos = this.modal.open(ModalDocumentosAnexosComponent, modalConfig);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los documentos anexos');
                    }
                );
                break
            default:
                this._documentosService.obtenerDocumentosAnexos(registro).subscribe(
                    response => {
                        this.loading(false);
                        item.get_documentos_anexos = response.data;
            
                        const modalConfig = new MatDialogConfig();
                        modalConfig.autoFocus = true;
                        modalConfig.width = '800px';
                        modalConfig.data = {
                            item: item,
                            parent: this,
                            proceso: 'emision',
                            subproceso: 'emisionCargaDocumentosAnexos'
                        };
                        this.modalDocumentosAnexos = this.modal.open(ModalDocumentosAnexosComponent, modalConfig);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los documentos anexos');
                    }
                );
                break;
        }
    }

    /**
     * Apertura una ventana modal para reemplazar el PDF asociado al documento electrónico.
     *
     * @param item Objeto con la información del registro
     */
    public openModalReemplazarPdf(item: any): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '500px';
        modalConfig.data = {
            item: item,
            parent: this,
            proceso: 'emision'
        };
        this.modalReemplazarPdf = this.modal.open(ModalReemplazarPdfComponent, modalConfig);
    }

    /**
     * Determina si debe mostrarse el icono en color Rojo que permite modificar un documento.
     *
     * @param data Objeto con la información del registro
     */
    checkIconModificarDocumentoSinPickup(data) {
        let express                   = data.ofe_identificacion === '860502609' ? true : false; // DHL Express
        let permisoModificarDocumento = this._auth.existePermiso(this.aclsUsuario.permisos, 'ModificarDocumentoPickupCash');
        let tipoFC                    = data.cdo_clasificacion === 'FC' ? true : false;
        let noProcesado               = data.cdo_procesar_documento !== 'SI';
        let RG9                       = data.cdo_representacion_grafica_documento == '9';

        // Si no hay estado PICKUP-CASH se muestra el icono rojo
        let sinPickupCash = false;
        if (data.estado_pickup_cash == "" && data.cdo_procesar_documento !== 'SI') {
            sinPickupCash = true;
        }

        return (express && permisoModificarDocumento && tipoFC && noProcesado && RG9 && sinPickupCash);
    }

    /**
     * Determina si debe mostrarse el icono en color Azul que permite modificar un documento.
     *
     * @param data Objeto con la información del registro
     */
    checkIconModificarDocumentoConPickupNoFinalizado(data) {
        let express = data.ofe_identificacion === '860502609' ? true : false; // DHL Express
        let permisoModificarDocumento = this._auth.existePermiso(this.aclsUsuario.permisos, 'ModificarDocumentoPickupCash');
        let tipoFC = data.cdo_clasificacion === 'FC' ? true : false;
        let noProcesado = data.cdo_procesar_documento !== 'SI';
        let RG9 = data.cdo_representacion_grafica_documento == '9';
        // Si no hay estado PICKUP-CASH se muestra el icono azul
        let pickupCash = false;
        if (data.estado_pickup_cash == "PICKUP_CASH_PROCESO" && data.cdo_procesar_documento !== 'SI') {
            pickupCash = true;
        }

        return (express && permisoModificarDocumento && tipoFC && noProcesado && RG9 && pickupCash);
    }

    /**
     * Determina si debe mostrarse el icono en color Verde que permite modificar un documento.
     *
     * @param data Información del registro del documento
     */
    checkIconModificarDocumentoConPickupFinalizado(data) {
        let express = data.ofe_identificacion === '860502609' ? true : false; // DHL Express
        let permisoModificarDocumento = this._auth.existePermiso(this.aclsUsuario.permisos, 'ModificarDocumentoPickupCash');
        let tipoFC = data.cdo_clasificacion === 'FC' ? true : false;
        let noProcesado = data.cdo_procesar_documento !== 'SI';
        let RG9 = data.cdo_representacion_grafica_documento == '9';
        // Si no hay estado PICKUP-CASH se muestra el icono verde
        let pickupCash = false;
        if (data.estado_pickup_cash == "PICKUP_CASH_FINALIZADO" && data.cdo_procesar_documento !== 'SI') {
            pickupCash = true;
        }

        return (express && permisoModificarDocumento && tipoFC && noProcesado && RG9 && pickupCash);
    }

    /**
     * Carga el componente que permite modificar un documento.
     *
     * @param data Información del registro del documento
     */
    public modificarDocumento(data, pickupcash): void {
        this._router.navigate([`emision/documentos-cco/editar-documento/factura/${data.ofe_id}/${data.cdo_id}/${pickupcash}`]);
    }

    /**
     * Carga el componente que permite ver un documento.
     *
     * @param data Información del registro del documento
     */
    public verDocumento(data): void {
        let pickupcash: any = null;
        if(this.checkIconModificarDocumentoSinPickup(data))
            pickupcash = 'sin-pickupcash';
        else if(this.checkIconModificarDocumentoConPickupNoFinalizado(data))
            pickupcash = 'pickupcash-no-finalizado';
        else if(this.checkIconModificarDocumentoConPickupFinalizado(data))
            pickupcash = 'pickupcash-finalizado';


        this._router.navigate([`emision/documentos-cco/ver-documento/factura/${data.ofe_id}/${data.cdo_id}/${pickupcash}`]);
    }

    /**
     * Carga el componente que permite modificar un documento de facturación web.
     *
     * @param data Información del registro del documento
     */
    public editarDocumentoFacturacionWeb(data): void {
        if (data.estado == 'INACTIVO') {
            swal({
                html: '<h2>No es posible editar el documento electrónico con estado INACTIVO.</h2>',
                type: 'info',
                confirmButtonClass: 'btn btn-success',
                confirmButtonText: 'Aceptar',
                buttonsStyling: false,
                allowOutsideClick: false
            }).catch(swal.noop);
        } else {
            if (data.cdo_clasificacion === 'FC') {
                this._router.navigate([`facturacion-web/editar-documento/factura/${data.ofe_id}/${data.cdo_id}`]);
            } else if (data.cdo_clasificacion === 'NC') {
                this._router.navigate([`facturacion-web/editar-documento/nota-credito/${data.ofe_id}/${data.cdo_id}`]);
            } else if (data.cdo_clasificacion === 'ND') {
                this._router.navigate([`facturacion-web/editar-documento/nota-debito/${data.ofe_id}/${data.cdo_id}`]);
            } else if (data.cdo_clasificacion === 'DS') {
                this._router.navigate([`facturacion-web/editar-documento/documento-soporte/${data.ofe_id}/${data.cdo_id}`]);
            } else if (data.cdo_clasificacion === 'DS_NC') {
                this._router.navigate([`facturacion-web/editar-documento/ds-nota-credito/${data.ofe_id}/${data.cdo_id}`]);
            }
        }
    }

    /**
     * Permite visualizar el documento electrónico en el formulario de facturación web.
     *
     * @param {*} data Información del registro del documento
     * @memberof DocumentosTrackingComponent
     */
    public verDocumentoFacturacionWeb(data): void {
        if (data.cdo_clasificacion === 'FC') {
            this._router.navigate([`facturacion-web/ver-documento/factura/${data.ofe_id}/${data.cdo_id}`]);
        } else if (data.cdo_clasificacion === 'NC') {
            this._router.navigate([`facturacion-web/ver-documento/nota-credito/${data.ofe_id}/${data.cdo_id}`]);
        } else if (data.cdo_clasificacion === 'ND') {
            this._router.navigate([`facturacion-web/ver-documento/nota-debito/${data.ofe_id}/${data.cdo_id}`]);
        } else if (data.cdo_clasificacion === 'DS') {
            this._router.navigate([`facturacion-web/ver-documento/documento-soporte/${data.ofe_id}/${data.cdo_id}`]);
        } else if (data.cdo_clasificacion === 'DS_NC') {
            this._router.navigate([`facturacion-web/ver-documento/ds-nota-credito/${data.ofe_id}/${data.cdo_id}`]);
        }
    }

    /**
     * Recargar el listado de documentos del tracking.
     *
     * @memberof DocumentosTrackingComponent
     */
    public recargarListaDocumentos() {
        this.recargarLista.next(true);
    }

    /**
     * Determina si existe un estado TRANSMISIONERP el cual puede ser exitoso, fallido simplemente existir en la base de datos.
     *
     * @param {object} data Información del registro del documento
     * @param {string} resultado Resultado del estado que se espera encontrar
     * @return {bool} Indicador de existencio o no del estado
     */
    checkTransmisionErp(data, resultado = null) {
        if (data.estado_transmisionerp != '') {
            if (resultado !== null) {
                if (data.estado_transmisionerp == resultado) 
                    return true;
            } else {
                return true;
            }
            
        }

        return false;
    }

    /**
     * Determina si existe un estado TRANSMISIONOPENCOMEX el cual puede ser exitoso, fallido simplemente existir en la base de datos.
     *
     * @param {object} data Información del registro del documento
     * @param {string} resultado Resultado del estado que se espera encontrar
     * @return {bool} Indicador de existencio o no del estado
     */
    checkTransmisionOpenComex(data, resultado = null) {
        if (data.estado_transmision_opencomex != '') {
            if (resultado !== null) {
                if (data.estado_transmision_opencomex == resultado) 
                    return true;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Actualiza el cotenido del array de acciones en bloque.
     *
     * @param {array} accionesBloque Array de acciones en bloque
     * @memberof DocumentosTrackingComponent
     */
    actualizarAccionesLote(accionesBloque) {
        this.accionesLote = accionesBloque
        this.accionesLote = [...this.accionesLote];
    }

    /**
     * Hace el proceso de conexión y validación al visor en Ecm.
     * 
     * @param {*} row Registro del documento en el tracking
     * @param {string} [origen] Origen del componente padre
     * @memberof DocumentosTrackingComponent
     */
    public validarVisorEcm(row, origen?: string) {
        let ofe            = this._openEcm.dataOfeVisorEcm();
        let ofeEcmConexion = JSON.parse(ofe.ofe_integracion_ecm_conexion)
        let ofeEcmAuth     = JSON.parse(ofe.integracion_variable_ecm_auth);
        let urlLogin       = ofeEcmConexion.url_api + ofeEcmAuth.endpoint_login_visor;
        let ofeConexion    = {'id_servicio':'','id_sitio':'','id_grupo':''};

        ofeEcmConexion.servicios.forEach(data => {
            if(data.modulo == 'Emision' && origen != 'recibidos'){
                ofeConexion.id_servicio = data.id_servicio;
                ofeConexion.id_sitio    = data.id_sitio;
                ofeConexion.id_grupo    = data.id_grupo;
            }
            if(data.modulo == 'Recepcion' && origen == 'recibidos'){
                ofeConexion.id_servicio = data.id_servicio;
                ofeConexion.id_sitio    = data.id_sitio;
                ofeConexion.id_grupo    = data.id_grupo;
            }
        });

        let formWithAction = new Object;
        formWithAction['usu_email'] = this.usuario.email;
        this._openEcm.loginECM(urlLogin, formWithAction).subscribe(
            res => {
                let urlVisorEcm = res.message + '/'+row.cdo_cufe+'/'+ofeEcmConexion.id_negocio_ecm+'/'+ofeConexion.id_servicio+'/'+ofeConexion.id_sitio+'/'+ofeConexion.id_grupo+'/NULL';
                let nX      = screen.width;
                let nY      = screen.height;
                let nNx     = 0;
                let nNy     = 0;
                let cWinOpt = "width="+nX+",scrollbars=1,resizable=YES,height="+nY+",left="+nNx+",top="+nNy;
                let cNomVen = 'zWinTrp'+Math.ceil(Math.random()*1000);
                window.open(urlVisorEcm,cNomVen,cWinOpt);
            },
            error => {
                swal({
                    html: '<h2>Error al iniciar sesión en openECM</h2>',
                    type: 'error',
                    confirmButtonClass: 'btn btn-danger',
                    confirmButtonText: 'Ok',
                    buttonsStyling: false,
                    allowOutsideClick: false
                }).catch(swal.noop);
            }
        );
    }

    /**
     * Determina si debe mostrarse el icono de ver para facturación web.
     *
     * @param data Información del documento
     */
    checkIconVerFacturacionWeb(data) {
        let permisoVer = false;
        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') ||
            (
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebVerFactura') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebVerNotaCredito') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebVerNotaDebito') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebVerDocumentoSoporte') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebVerNotaCreditoDS')
            )
        ) {
            permisoVer = true;
        }

        let pickupCash = true;
        if (data.ofe_identificacion === '860502609' && data.cdo_clasificacion === 'FC' && 
            data.cdo_procesar_documento !== 'SI' && data.cdo_representacion_grafica_documento == '9'
        ) {
            pickupCash = false;
        }

        return (permisoVer && pickupCash);
    }

    /**
     * Determina si debe mostrarse el icono de editar para facturación web.
     *
     * @param data Información del documento
     */
    checkIconEditarFacturacionWeb(data) {
        let permisoEditar = false;
        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') ||
            (
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebEditarFactura') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebEditarNotaCredito') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebEditarNotaDebito') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebEditarDocumentoSoporte') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'FacturacionWebEditarNotaCreditoDS')
            )
        ) {
            permisoEditar= true;
        }

        let pickupCash = true;
        if (data.ofe_identificacion === '860502609' && data.cdo_clasificacion === 'FC' && 
            data.cdo_procesar_documento !== 'SI' && data.cdo_representacion_grafica_documento == '9'
        ) {
            pickupCash = false;
        }

        return (permisoEditar && pickupCash);
    }

    /**
     * Carga el componente que permite modificar un documento no electrónico.
     *
     * @param data Información del registro del documento
     */
    public editarDocumentoNoElectronico(data): void {
        if (data.estado == 'INACTIVO') {
            swal({
                html: '<h2>No es posible editar el documento no electrónico con estado INACTIVO.</h2>',
                type: 'info',
                confirmButtonClass: 'btn btn-success',
                confirmButtonText: 'Aceptar',
                buttonsStyling: false,
                allowOutsideClick: false
            }).catch(swal.noop);
        } else {
            this._router.navigate([`recepcion/documentos-no-electronicos/editar-documento/${data.ofe_id}/${data.cdo_id}`]);
        }
    }

    /**
     * Permite visualizar el documento no electrónico en el formulario.
     *
     * @param {*} data Información del registro del documento
     * @memberof DocumentosTrackingComponent
     */
    public verDocumentoNoElectronico(data): void {
        this._router.navigate([`recepcion/documentos-no-electronicos/ver-documento/${data.ofe_id}/${data.cdo_id}`]);
    }

    /**
     * Determina si debe mostrarse el icono de ver un documento no electrónico.
     *
     * @param data Información del documento
     */
    checkIconVerDocumentoNoElectronico(data) {
        let permisoVer = false;
        if ((this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionDocumentoNoElectronicoVer')) && data.cdo_origen == 'NO-ELECTRONICO') {
            permisoVer = true;
        }

        return permisoVer;
    }

    /**
     * Determina si debe mostrarse el icono de editar un documento no electrónico.
     *
     * @param data Información del documento
     */
    checkIconEditarDocumentoNoElectronico(data) {
        let permisoEditar = false;
        if ((this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionDocumentoNoElectronicoEditar')) && data.cdo_origen == 'NO-ELECTRONICO') {
            permisoEditar = true;
        }

        return permisoEditar;
    }

    /**
     * Apertura una ventana modal para mostrar la información del correo recibido.
     *
     * @param item Objeto con la información del registro
     */
    public openModalCorreoRecibido(item: any): void {
        this.loading(true);
        let registro = {
            epm_id: item.epm_id
        };
        this._correosRecibidosService.obtenerCorreoRecibido(registro).subscribe(
            response => {
                this.loading(false);
                const modalConfig = new MatDialogConfig();
                modalConfig.autoFocus = true;
                modalConfig.width = '800px';
                modalConfig.data = {
                    item: response.data,
                    parent: this
                };
                this.modalCorreoRecibido = this.modal.open(ModalCorreosRecibidosComponent, modalConfig);
            },
            error => {
                this.mostrarErrores(error, 'Error al consultar los detalles del correo recibido');
            }
        );
    }

    /**
     * Descarga los anexos del correo recibido.
     *
     * @param {*} row Registro seleccionado
     * @memberof DocumentosTrackingComponent
     */
    public descargarCorreoRecibido(row){
        this.loading(true);
        this._correosRecibidosService.descargarAnexosCorreo(row.epm_id).subscribe(
            response => {
                this.loading(false);
            },
            error => {
                this.loading(false);
            }
        );
    }

    /**
     * Asociar anexos con un documento electrónico.
     *
     * @param {*} row Registro seleccionado
     * @memberof DocumentosTrackingComponent
     */
    public asociarAnexoCorreoRecibido(row){
        this._router.navigate([`recepcion/documentos-anexos/cargar-anexos/asociar/${row.epm_id}`]);
    }

    /**
     * Asociar anexos con un documento electrónico.
     *
     * @param {*} row Registro seleccionado
     * @memberof DocumentosTrackingComponent
     */
    public crearDocumentoAsociarAnexo(row){
        this._router.navigate([`recepcion/documentos-manuales/asociar/${row.epm_id}`]);
    }

    /**
     * Recarga la consulta de documentos.
     *
     * @memberof DocumentosTrackingComponent
     */
    public recargarConsulta() {
        this.buscar = "";
        this.searchinline();
    }

    /**
     * Determina si debe mostrar el icono para el estado de validación pendiente, validado, rechazado, pagado.
     *
     * @param {*} data Información del documento que incluye los estados del mismo
     * @param {string} estadoComparar Nombre del estado que se pretende validar
     * @return {boolean} 
     * @memberof DocumentosTrackingComponent
     */
    checkEstadoValidacion(data: any, estadoComparar: string) {
        if(data.estado_validacion && data.estado_validacion == estadoComparar)
            return true;
        else
            return false;
    }
}
