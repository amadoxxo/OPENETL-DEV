import { Component, Input, OnInit, ViewChild } from '@angular/core';
import { DatatableComponent } from '@swimlane/ngx-datatable';
import { Auth } from '../../../services/auth/auth.service';
import { Router } from '@angular/router';
import { GridInterface } from '../../core/grid_Interface';
import { NgSelectComponent } from '@ng-select/ng-select';
import { MatDialog , MatDialogConfig} from "@angular/material/dialog";
import { JwtHelperService } from '@auth0/angular-jwt';
import { BsdConstants } from '../../core/bsd_constants';
import { BaseComponentList } from '../../core/base_component_list';
import { DocumentosTrackingRecepcionColumnInterface, DocumentosTrackingRecepcionInterface } from './documentos-tracking-recepcion-interface';
import { ModalResumenEstadosDocumentoComponent } from "../modal-resumen-estados-documento/modal-resumen-estados-documento.component";
import { ModalNotificacionDocumentoComponent } from "../modal-notificacion-documento/modal-notificacion-documento.component";
import { ModalDocumentosAnexosComponent } from '../../modals/modal-documentos-anexos/modal-documentos-anexos.component';
import { OpenEcmService } from '../../../services/ecm/openecm.service';
import { DocumentosService } from '../../../services/emision/documentos.service';
import { DocumentosRecibidosService } from '../../../services/recepcion/documentos_recibidos.service';
import swal from 'sweetalert2';

/**
 * Componente desarrollado para ser utilizado exclusivamente en los trackings de documentos recibidos y validación de documentos
 * Para cualquier otro tracking se debe utilizar el componente DocumentosTrackingComponent
 */
@Component({
    selector: 'app-documentos-tracking-recepcion',
    templateUrl: './documentos-tracking-recepcion.component.html',
    styleUrls: ['./documentos-tracking-recepcion.component.scss']
})
export class DocumentosTrackingRecepcionComponent extends BaseComponentList implements OnInit, GridInterface {
    @ViewChild('tracking', {static: true}) tracking: DatatableComponent;
    @ViewChild('selectAcciones') ngSelectComponent: NgSelectComponent;

    @Input() trackingRecepcionInterface: DocumentosTrackingRecepcionInterface = null;
    @Input() columns                   : DocumentosTrackingRecepcionColumnInterface [] = [];
    @Input() rows                      : any [] = [];
    @Input() accionesLote              : any [] = [];
    @Input() arrDescargas              : any [] = [];
    @Input() arrEnvioCorreo            : any [] = [];
    @Input() arrReenvioNotificacion    : any [] = [];
    @Input() multiSelect               : boolean = true;
    @Input() loadingIndicator          : boolean = false;
    @Input() totalElements             : number;
    @Input() existeConsulta            : boolean;
    @Input() tipo                      : string;
    @Input() visorEcm                  : boolean = false;
    @Input() linkAnterior              : string;
    @Input() linkSiguiente             : string;
    @Input() totalShow                 = BsdConstants.INIT_SIZE_SEARCH;

    public usuario                 : any;
    public tiposDescargas          : any;
    public tiposReenvioNotificacion: any;
    public selected                = [];
    public allRowsSelected         : any[];
    public length                  = BsdConstants.INIT_SIZE_SEARCH;
    public buscar                  : any;
    public columnaOrden            : string;
    public ordenDireccion          : string;
    public reorderable             : boolean;
    public paginationSize          : any;
    public maxDate                 = new Date();
    public page                    = 0;
    public aclsUsuario             : any;
    public selectedOption          : any;
    public tamanoArchivoSuperior   : any;

    private modalEstados         : any;
    private modalNotificacion    : any;
    private modalDocumentosAnexos: any;
    private modalReemplazarPdf   : any;
    private modalCorreoRecibido  : any;

    /**
     * Mensajes para la tabla principal de los listados
     */
    public messageDT = {
        emptyMessage   : 'No hay data para mostrar',
        totalMessage   : 'total',
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
        private _documentosRecepcionService: DocumentosRecibidosService,
    ) {
        super();
        this.paginationSize = [
            {label: '10',    value: 10},
            {label: '25',    value: 25},
            {label: '50',    value: 50},
            {label: '100',   value: 100}
        ];
    }

    ngOnInit(): void {
        this.usuario     = this.jwtHelperService.decodeToken();
        this.aclsUsuario = this._auth.getAcls();
    }

    /**
     * Permite controlar la paginación
     * @param page
     */
    public onPage(page) {
        if (page && this.trackingRecepcionInterface)
            this.trackingRecepcionInterface.onPage(page);
    }

    /**
     * Permite controlar el ordenamiento por una columna
     * @param $evt
     */
    public onSort($evt) {
        if (this.trackingRecepcionInterface) {
            let column          = $evt.column.prop;
            this.columnaOrden   = column;
            this.ordenDireccion = $evt.newValue.toUpperCase();
            this.trackingRecepcionInterface.onOrderBy(this.columnaOrden, this.ordenDireccion);
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
     * Cambia el numero de items a mostrar y refresca la grid
     * @param size
     */
    paginar(size) {
        if (this.trackingRecepcionInterface) {
            this.length = size;
            this.trackingRecepcionInterface.onChangeSizePage(this.length);
        }
    }

    /**
     * Evento para descargar un documento.
     * 
     * @param item
     */
    descargar() {
        if (this.trackingRecepcionInterface) {
            let copy = Object.assign([], this.selected);
            this.trackingRecepcionInterface.onDescargarItems(copy, this.tiposDescargas);
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
        if (this.trackingRecepcionInterface) {
            let copy = Object.assign([], this.selected);
            this.trackingRecepcionInterface.onReenvioNotificacion(copy, this.tiposReenvioNotificacion);
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
        if (this.trackingRecepcionInterface) {
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
                    this.trackingRecepcionInterface.onAgendarReporteBackground();
                } else {
                    this.trackingRecepcionInterface.onDescargarExcel();
                }
            }).catch(swal.noop);
        }
    }

    /**
     * Evento para el combo de Acciones en Bloque.
     * 
     * @param item
     */
    accionesBloque(evt, row: any = undefined) {
        if (evt && this.trackingRecepcionInterface && !row){
            let copy = Object.assign([], this.selected);
            this.trackingRecepcionInterface.onOptionMultipleSelected(this.selectedOption, copy);
            this.tracking.selected = [];
            this.selected.length   = 0;
            this.selectedOption    = null;
            this.ngSelectComponent.handleClearClick();
        } else if(row) {
            this.trackingRecepcionInterface.onOptionMultipleSelected(evt.id, [row]);
            this.tracking.selected = [];
            this.selected.length   = 0;
            this.selectedOption    = null;
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
        if (this.trackingRecepcionInterface)
            this.trackingRecepcionInterface.onOptionItem(item, opcion);
    }

    /**
     * Determina si debe mostrarse el icono de notificación para los estados existentes.
     *
     * @param data Objeto con la información del registro
     */
    checkEventosNotificadoRecepcion(data) {
        if (
            data.cdo_estado_notificacion === 'NOTACUSERECIBO' || data.cdo_estado_notificacion === 'NOTRECIBOBIEN' ||
            data.cdo_estado_notificacion === 'NOTACEPTACION' || data.cdo_estado_notificacion === 'NOTRECHAZO'
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
            (
                data.cdo_estado_notificacion === 'NOTACUSERECIBO' || data.cdo_estado_notificacion === 'NOTRECIBOBIEN' ||
                data.cdo_estado_notificacion === 'NOTACEPTACION' || data.cdo_estado_notificacion === 'NOTRECHAZO'
            ) && data.cdo_estado_notificacion_resultado === 'FALLIDO'
        ) {
            return false;
        }

        return true;
    }

    /**
     * Ubica el estado RDI EXITOSO para verificar si el estado tieno o no inconsistencias.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoRdiInconsistencias(data) {
        if (data.cdo_rdi === 'INCONSISTENCIA')
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Aceptado Dian.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoDian(data) {
        if (data.cdo_estado_dian && data.cdo_estado_dian === 'APROBADO')
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Aceptado Dian con Notificación.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoDianNotificacion(data) {
        if (data.cdo_estado_dian && data.cdo_estado_dian === 'CONNOTIFICACION')
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Rechazado Dian.
     *
     * @param data
     */
    checkEstadoRechazadoDian(data) {
        if (data.cdo_estado_dian && data.cdo_estado_dian === 'RECHAZADO')
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de GetStatus Pendiente.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoGetStatusWarning(data) {
        if (data.cdo_estado_dian && data.cdo_estado_dian === 'ENPROCESO')
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Aceptado exitoso.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptado(data) {
        if (
            data.cdo_estado_eventos_dian && data.cdo_estado_eventos_dian === 'ACEPTACION' &&
            data.cdo_estado_eventos_dian_resultado && data.cdo_estado_eventos_dian_resultado === 'EXITOSO'
        )
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Aceptado fallido.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoFallido(data) {
        if (
            data.cdo_estado_eventos_dian && data.cdo_estado_eventos_dian === 'ACEPTACION' &&
            data.cdo_estado_eventos_dian_resultado && data.cdo_estado_eventos_dian_resultado === 'FALLIDO'
        )
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Aceptado Tácitamente exitoso.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoTacitamente(data) {
        if (
            data.cdo_estado_eventos_dian && data.cdo_estado_eventos_dian === 'ACEPTACIONT' &&
            data.cdo_estado_eventos_dian_resultado && data.cdo_estado_eventos_dian_resultado === 'EXITOSO'
        )
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Aceptado Tácitamente fallido.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoAceptadoTacitamenteFallido(data) {
        if (
            data.cdo_estado_eventos_dian && data.cdo_estado_eventos_dian === 'ACEPTACIONT' &&
            data.cdo_estado_eventos_dian_resultado && data.cdo_estado_eventos_dian_resultado === 'FALLIDO'
        )
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para el estado de Rechazado exitoso.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoRechazado(data) {
        if (
            data.cdo_estado_eventos_dian && data.cdo_estado_eventos_dian === 'RECHAZO' &&
            data.cdo_estado_eventos_dian_resultado && data.cdo_estado_eventos_dian_resultado === 'EXITOSO'
        )
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Rechazado fallido.
     *
     * @param data Objeto con la información del registro
     */
    checkEstadoRechazadoFallido(data) {
        if (
            data.cdo_estado_eventos_dian && data.cdo_estado_eventos_dian === 'RECHAZO' &&
            data.cdo_estado_eventos_dian_resultado && data.cdo_estado_eventos_dian_resultado === 'FALLIDO'
        )
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para documentos anexos.
     *
     * @param data Objeto con la información del registro
     */
    checkDocumentosAnexos(data) {
        if (data.cdo_documentos_anexos && data.cdo_documentos_anexos === 1)
            return true;

        return false;
    }

    /**
     * Apertura una ventana modal para ver el resumen de estados de un documento.
     *
     * @param data Objeto con la información del registro
     * @param estadoEventoAceptadoRechazado Indica si se debe mostrar la información de aceptación/rechazo del documento en el proceso de emisión y recepción
     * @param estadoTransmisionErp Indica si se debe mostrar la información de transmisión al ERP del documento en el proceso de recepción
     * @param estadoTransmisionOpenComex Indica si se debe mostrar la información de transmisión a openCOMEX del documento en el proceso de recepción
     * @param proceso Indica el proceso relacionado con la consulta, por ejemplo recepcion - emision - validacion-documentos
     */
    public openModalEstados(data, estadoEventoAceptadoRechazado = undefined, estadoTransmisionErp = undefined, estadoTransmisionOpenComex = undefined, proceso = undefined): void {
        this.loading(true);

        data.estadoTransmisionErp          = estadoTransmisionErp;
        data.estadoTransmisionOpenComex    = estadoTransmisionOpenComex;
        data.estadoEventoAceptadoRechazado = estadoEventoAceptadoRechazado;
        let registro = {};
        switch (this.tipo) {
            case 'recibidos':
            case 'validacion-documentos':
                data.proceso = proceso ? proceso : ((this.tipo == 'recibidos') ? 'recepcion' : 'validacion-documentos');
                registro = {
                    cdo_id: data.cdo_id,
                    tracking: this.tipo
                };

                this._documentosRecepcionService.obtenerEstadosDocumento(registro).subscribe({
                    next: response => {
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
                    error: error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los estados del documento');
                    }
                });
                break
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
            cdo_id: data.cdo_id,
            tracking: this.tipo
        };

        switch (this.tipo) {
            case 'recibidos':
                this._documentosRecepcionService.obtenerEstadosDocumento(registro).subscribe({
                    next: response => {
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
                    error: error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los estados de notificación del documento');
                    }
                });
                break;
            default:
                this._documentosService.obtenerEstadosDocumento(registro).subscribe({
                    next: response => {
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
                    error: error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los estados de notificación del documento');
                    }
                });
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
            case 'validacion-documentos':
                this._documentosRecepcionService.obtenerDocumentosAnexos(registro).subscribe({
                    next: response => {
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
                    error: error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al consultar los documentos anexos');
                    }
                });
                break
        }
    }

    /**
     * Determina si existe un estado TRANSMISIONERP el cual puede ser exitoso, fallido simplemente existir en la base de datos.
     *
     * @param {object} data Información del registro del documento
     * @param {string} resultado Resultado del estado que se espera encontrar
     * @return {bool} Indicador de existencio o no del estado
     */
    checkTransmisionErp(data, resultado = null) {
        if (data.cdo_estado_transmisionerp) {
            if (resultado !== null) {
                if (data.cdo_estado_transmisionerp == resultado) 
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
        if (data.cdo_estado_transmision_opencomex) {
            if (resultado !== null) {
                if (data.cdo_estado_transmision_opencomex == resultado) 
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
     * @memberof DocumentosTrackingRecepcionComponent
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
     * @memberof DocumentosTrackingRecepcionComponent
     */
    validarVisorEcm(row, origen?: string) {
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
        this._openEcm.loginECM(urlLogin, formWithAction).subscribe({
            next: res => {
                let urlVisorEcm = res.message + '/'+row.cdo_cufe+'/'+ofeEcmConexion.id_negocio_ecm+'/'+ofeConexion.id_servicio+'/'+ofeConexion.id_sitio+'/'+ofeConexion.id_grupo+'/NULL';
                let nX      = screen.width;
                let nY      = screen.height;
                let nNx     = 0;
                let nNy     = 0;
                let cWinOpt = "width="+nX+",scrollbars=1,resizable=YES,height="+nY+",left="+nNx+",top="+nNy;
                let cNomVen = 'zWinTrp'+Math.ceil(Math.random()*1000);
                window.open(urlVisorEcm,cNomVen,cWinOpt);
            },
            error: error => {
                swal({
                    html: '<h2>Error al iniciar sesión en openECM</h2>',
                    type: 'error',
                    confirmButtonClass: 'btn btn-danger',
                    confirmButtonText: 'Ok',
                    buttonsStyling: false,
                    allowOutsideClick: false
                }).catch(swal.noop);
            }
        });
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
                type: 'error',
                confirmButtonClass: 'btn btn-danger',
                confirmButtonText: 'Aceptar',
                buttonsStyling: false,
                allowOutsideClick: false
            }).catch(swal.noop);
        } else {
            if(data.ofe_recepcion_fnc_activo === 'SI') {
                if(data.estado_validacion === undefined || data.estado_validacion === '' || data.estado_validacion === 'RECHAZADO') {
                    this._router.navigate([`recepcion/documentos-no-electronicos/editar-documento/${data.ofe_id}/${data.cdo_id}`]);
                } else {
                    swal({
                        html: '<h2>No es posible editar el documento no electrónico porque su estado VALIDACIÓN es [' + data.estado_validacion + '].</h2>',
                        type: 'error',
                        confirmButtonClass: 'btn btn-danger',
                        confirmButtonText: 'Aceptar',
                        buttonsStyling: false,
                        allowOutsideClick: false
                    }).catch(swal.noop);
                }
            } else {
                this._router.navigate([`recepcion/documentos-no-electronicos/editar-documento/${data.ofe_id}/${data.cdo_id}`]);
            }
        }
    }

    /**
     * Permite visualizar el documento no electrónico en el formulario.
     *
     * @param {*} data Información del registro del documento
     * @memberof DocumentosTrackingRecepcionComponent
     */
    verDocumentoNoElectronico(data): void {
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
     * Determina si debe mostrar el icono para el estado de validación pendiente, validado, rechazado, pagado.
     *
     * @param {*} data Información del documento que incluye los estados del mismo
     * @param {string} estadoComparar Nombre del estado que se pretende validar
     * @return {boolean} 
     * @memberof DocumentosTrackingRecepcionComponent
     */
    checkEstadoValidacion(data: any, estadoComparar: string) {
        if(data.cdo_validacion && data.cdo_validacion == estadoComparar)
            return true;
        else
            return false;
    }
}
