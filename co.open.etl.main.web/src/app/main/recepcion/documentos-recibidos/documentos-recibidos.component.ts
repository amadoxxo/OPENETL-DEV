import { Component, OnInit, ViewChild } from '@angular/core';
import { AbstractControl, FormGroup, FormBuilder } from '@angular/forms';
import { Auth } from '../../../services/auth/auth.service';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';
import { concat, Observable, of, Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, filter, switchMap, tap, catchError } from 'rxjs/operators';
import { BaseComponentList } from 'app/main/core/base_component_list';
import { DocumentosTrackingRecepcionComponent } from './../../commons/documentos-tracking-recepcion/documentos-tracking-recepcion.component';
import { DocumentosTrackingRecepcionColumnInterface, DocumentosTrackingRecepcionInterface } from '../../commons/documentos-tracking-recepcion/documentos-tracking-recepcion-interface';
import { ModalDocumentosListaComponent } from '../../modals/modal-documentos-lista/modal-documentos-lista.component';
import { ModalEventosDocumentosComponent } from '../../commons/modal-eventos-documentos/modal-eventos-documentos.component';
import { ModalAsignarGrupoTrabajoDocumentosComponent } from '../../commons/modal-asignar-grupo-trabajo-documentos/modal-asignar-grupo-trabajo-documentos.component';
import { DocumentosRecibidosService } from '../../../services/recepcion/documentos_recibidos.service';
import { CommonsService } from '../../../services/commons/commons.service';
import { OpenEcmService } from '../../../services/ecm/openecm.service';
import { RadianService } from '../../../services/radian/radian.service';
import { ReportesBackgroundService } from '../../../services/reportes/reportes_background.service';
import { BaseService } from '../../../services/core/base.service';
import { DatosParametricosValidacionService } from './../../../services/proyectos-especiales/recepcion/fnc/validacion/datos-parametricos-validacion.service';
import { JwtHelperService } from '@auth0/angular-jwt';
import { ConfiguracionService } from 'app/services/configuracion/configuracion.service';
import { Usuario } from 'app/main/models/usuario.model';
import { NgSelectComponent } from '@ng-select/ng-select';
import * as moment from 'moment';
import swal from 'sweetalert2';

class Parameters {
    cdo_fecha_desde                   : string;
    cdo_fecha_hasta                   : string;
    cdo_fecha_validacion_dian_desde   : string;
    cdo_fecha_validacion_dian_hasta   : string;
    ofe_id                            : number;
    pro_id?                           : number;
    cdo_lote?                         : string;
    cdo_cufe?                         : string;
    cdo_origen?                       : string;
    cdo_clasificacion?                : string;
    forma_pago?                       : string;
    cdo_consecutivo?                  : string;
    rfa_prefijo?                      : string;
    estado_eventos_dian?              : string;
    resultado_eventos_dian?           : string;
    estado_validacion?                : string;
    campo_validacion?                 : string;
    valor_campo_validacion?           : string;
    estado_acuse_recibo?              : string;
    estado_recibo_bien?               : string;
    estado_dian?                      : string;
    acuse_recibo?                     : string;
    recibo_bien_servicio?             : string;
    transmision_erp?                  : string;
    transmision_opencomex?            : string;
    filtro_grupos_trabajo?            : string;
    filtro_grupos_trabajo_usuario?    : string;
    cdo_usuario_responsable_recibidos?: number;
    estado?                           : string;
    length?                           : number;
    excel?                            : boolean;
    columnaOrden?                     : string;
    ordenDireccion?                   : string;
    pag_anterior?                     : string;
    pag_siguiente?                    : string;
}

@Component({
    selector: 'app-documentos-recibidos',
    templateUrl: './documentos-recibidos.component.html',
    styleUrls: ['./documentos-recibidos.component.scss']
})
export class DocumentosRecibidosComponent extends BaseComponentList implements OnInit, DocumentosTrackingRecepcionInterface {
    @ViewChild('documentosTrackingRecepcion', {static: true}) documentosTrackingRecepcion: DocumentosTrackingRecepcionComponent;
    @ViewChild('selectUsuarios', { static: true }) selectUsuarios: NgSelectComponent;

    public parameters : Parameters;
    public form       : FormGroup;
    public aclsUsuario: any;
    public usuario    : any;

    public arrOrigen  : Array<Object> = [
        {id: 'MANUAL',         name: 'MANUAL'},
        {id: 'RPA',            name: 'RPA'},
        {id: 'NO-ELECTRONICO', name: 'NO-ELECTRONICO'},
        {id: 'CORREO',         name: 'CORREO'}
    ];

    public arrDescargas: Array<Object> = [
        {id: 'pdf',                     name: 'PDF'},
        {id: 'xml-ubl',                 name: 'XML-UBL'},
        {id: 'ar_estado_dian',          name: 'AR ESTADO DIAN'},
        {id: '',                        name: '==========================================', disabled: true},
        {id: 'pdf_acuse_recibo',        name: 'PDF ACUSE DE RECIBO'},
        {id: 'pdf_recibo_bien',         name: 'PDF RECIBO BIEN Y/O PRESTACIÓN DEL SERVICIO'},
        {id: 'pdf_aceptacion_expresa',  name: 'PDF ACEPTACIÓN EXPRESA'},
        {id: 'pdf_reclamo_rechazo',     name: 'PDF RECLAMO (RECHAZO)'},
        {id: '',                        name: '==========================================', disabled: true},
        {id: 'ar_acuse_recibo',         name: 'AR ACUSE DE RECIBO'},
        {id: 'ar_recibo_bien',          name: 'AR RECIBO BIEN Y/O PRESTACIÓN DEL SERVICIO'},
        {id: 'ar_aceptacion_expresa',   name: 'AR ACEPTACIÓN EXPRESA'},
        {id: 'ar_reclamo_rechazo',      name: 'AR RECLAMO (RECHAZO)'},
        {id: '',                        name: '==========================================', disabled: true},
        {id: 'ad_acuse_recibo',         name: 'AD ACUSE DE RECIBO'},
        {id: 'ad_recibo_bien',          name: 'AD RECIBO BIEN Y/O PRESTACIÓN DEL SERVICIO'},
        {id: 'ad_aceptacion_expresa',   name: 'AD ACEPTACIÓN EXPRESA'},
        {id: 'ad_reclamo_rechazo',      name: 'AD RECLAMO (RECHAZO)'}
    ];

    public arrTipoDoc: Array<Object> = [
        {id: 'FC', name: 'FC'},
        {id: 'NC', name: 'NC'},
        {id: 'ND', name: 'ND'},
        {id: 'DS', name: 'DS'},
        {id: 'DS_NC', name: 'DS_NC'}
    ];

    public arrEstadoRegistro: Array<Object> = [
        {id: 'ACTIVO', name: 'ACTIVO'},
        {id: 'INACTIVO', name: 'INACTIVO'}
    ];

    public arrFormaPago: Array<any>    = [];

    public arrEstadoDoc: Array<Object> = [
        {id: 'sinestado',   name: 'SIN ESTADO'},
        {id: 'aceptaciont', name: 'ACEPTACIÓN TÁCITA'},
        {id: 'aceptacion',  name: 'ACEPTACIÓN EXPRESA'},
        {id: 'rechazo',     name: 'RECLAMO (RECHAZO)'}
    ];

    public arrEstadoResEvenDian: Array<Object> = [
        {id: 'exitoso', name: 'EXITOSO'},
        {id: 'fallido', name: 'FALLIDO'}
    ];

    public arrEstadoValidacion: Array<Object> = [
        {id: 'sin_gestion', name: 'SIN GESTIÓN'},
        {id: 'sin_gestion_rechazado', name: 'SIN GESTIÓN Y RECHAZADO'},
        {id: 'pendiente', name: 'PENDIENTE'},
        {id: 'validado',  name: 'VALIDADO'},
        {id: 'rechazado', name: 'RECHAZADO'},
        {id: 'pagado',    name: 'PAGADO'}
    ];

    public arrEstadoDian: Array<Object> = [
        {id: 'aprobado',        name: 'APROBADO'},
        {id: 'connotificacion', name: 'APROBADO CON NOTIFICACIÓN'},
        {id: 'rechazado',       name: 'RECHAZADO'},
        {id: 'enproceso',       name: 'EN PROCESO'}
    ];

    public arrReenvioNotificacion: Array<Object> = [
        {id: 'ACUSE',      name: 'ACUSE DE RECIBO'},
        {id: 'RECIBOBIEN', name: 'RECIBO BIEN Y/O PRESTACIÓN DEL SERVICIO'},
        {id: 'ACEPTACION', name: 'ACEPTACIÓN EXPRESA'},
        {id: 'RECLAMO',    name: 'RECLAMO (RECHAZO)'}
    ];

    public arrTransmisionErp: Array<Object> = [
        {id: 'sinestado', name: 'SIN ESTADO'},
        {id: 'exitoso',   name: 'EXITOSO'},
        {id: 'fallido',   name: 'FALLIDO'}
    ];

    public arrTransmisionOpencomex: Array<Object> = [
        {id: 'sinestado', name: 'SIN ESTADO'},
        {id: 'exitoso',   name: 'EXITOSO'},
        {id: 'fallido',   name: 'FALLIDO'}
    ];

    public arrFiltrosGruposTrabajo: Array<Object> = [
        {id: 'unico',      name: 'ÚNICO'},
        {id: 'compartido', name: 'COMPARTIDO'},
        {id: '',           name: 'AMBOS'}
    ];

    public arrGruposTrabajoUsuario: Array<any> = [];

    public arrAcuseRecibo: Array<Object> = [
        {id: 'SI', name: 'SÍ'},
        {id: 'NO', name: 'NO'}
    ];

    public arrReciboBien: Array<Object> = [
        {id: 'SI', name: 'SÍ'},
        {id: 'NO', name: 'NO'}
    ];

    public accionesBloque: Array<Object> = [];

    public ofe_id                           : AbstractControl;
    public pro_id                           : AbstractControl;
    public cdo_fecha_desde                  : AbstractControl;
    public cdo_fecha_hasta                  : AbstractControl;
    public cdo_fecha_validacion_dian_desde  : AbstractControl;
    public cdo_fecha_validacion_dian_hasta  : AbstractControl;
    public cdo_origen                       : AbstractControl;
    public cdo_clasificacion                : AbstractControl;
    public forma_pago                       : AbstractControl;
    public estado_eventos_dian              : AbstractControl;
    public resEventosDian                   : AbstractControl;
    public estado_validacion                : AbstractControl;
    public campo_validacion                 : AbstractControl;
    public valor_campo_validacion           : AbstractControl;
    public estado_acuse_recibo              : AbstractControl;
    public estado_recibo_bien               : AbstractControl;
    public estado_dian                      : AbstractControl;
    public acuse_recibo                     : AbstractControl;
    public recibo_bien_servicio             : AbstractControl;
    public cdo_lote                         : AbstractControl;
    public cdo_cufe                         : AbstractControl;
    public cdo_consecutivo                  : AbstractControl;
    public rfa_prefijo                      : AbstractControl;
    public transmision_erp                  : AbstractControl;
    public transmision_opencomex            : AbstractControl;
    public filtro_grupos_trabajo            : AbstractControl;
    public filtro_grupos_trabajo_usuario    : AbstractControl;
    public cdo_usuario_responsable_recibidos: AbstractControl;
    public estado                           : AbstractControl;

    public existeConsulta            : boolean = false;
    public visorEcm                  : boolean = false;
    public estadoVisorEcm            : boolean = false;
    public mostrarFiltroGruposTrabajo: boolean = false;

    //Modals
    private modalDocumentosLista     : any;
    private modalRechazos            : any;
    private modalAsignarGruposTrabajo: any;

    public ofes: Array<any> = [];
    public arrActoresRadian: Array<any>= [];

    public trackingRecepcionInterface: DocumentosTrackingRecepcionInterface;

    public registros: any [] = [];

    public _grupo_trabajo: any;

    public ofeRecepcionFncActivo : string = 'NO';
    public ofeRecepcionFncConfiguracion: any [] = [];

    public columns: DocumentosTrackingRecepcionColumnInterface[] = [
        {name: 'Lote', prop: 'cdo_lote', sorteable: false, width: '110'},
        {name: 'Tipo', prop: 'cdo_clasificacion', sorteable: false, width: '60'},
        {name: 'Documento', prop: 'documento', sorteable: false, width: '150'},
        {name: 'Emisor', prop: 'pro_razon_social', sorteable: false, width: '250'},
        {name: 'Fecha', prop: 'fecha', sorteable: true, width: '170'},
        {name: 'Moneda', prop: 'moneda', sorteable: false, width: '80'},
        {name: 'Valor', prop: 'valor_a_pagar', sorteable: false, width: '150', derecha: true},
        {name: 'Estado', prop: 'estado', sorteable: false, width: '100'},
        {name: 'Origen', prop: 'cdo_origen', sorteable: false, width: '120'},
        {name: 'Fecha Cargue', prop: 'fecha_creacion', sorteable: false, width: '170'},
    ];

    public mostrarSelectResEstadoDian       : boolean = true;
    public mostrarFiltroTransmisionErp      : boolean = false;
    public mostrarFiltroTransmisionOpencomex: boolean = false;
    public identificacionAgenciaAduanasDhl  : string  = '830076778';

    public tablaDatosParametricosValidacion : string = 'pry_datos_parametricos_validacion';
    public mostrarComboValorCampoValidacion : boolean = false;
    public arrCamposValidacion              : Array<Object> = [];
    public arrValoresCamposValidacion       : Array<Object> = [];

    public usuarios$       : Observable<Usuario[]>;
    public usuariosInput$  = new Subject<string>();
    public usuariosLoading = false;
    public selectedUsuId   : any;
    public paginador       : string;
    public linkSiguiente   : string;
    public linkAnterior    : string;

    /**
     * Constructor de DocumentosRecibidosComponent.
     * 
     * @param {Auth} _auth
     * @param {OpenEcmService} _openEcm
     * @param {FormBuilder} fb
     * @param {Router} _router
     * @param {MatDialog} modal
     * @param {BaseService} _baseService
     * @param {CommonsService} _commonsService
     * @param {JwtHelperService} jwtHelperService
     * @param {DocumentosRecibidosService} _documentosRecibidosService
     * @param {ReportesBackgroundService} _reportesBackgroundService
     * @param {DatosParametricosValidacionService} _datosParametricosService
     * @memberof DocumentosRecibidosComponent
     */
    constructor(
        private fb                         : FormBuilder,
        public _auth                       : Auth,
        private modal                      : MatDialog,
        public _openEcm                    : OpenEcmService,
        private _radianService             : RadianService,
        private _baseService               : BaseService,
        private _commonsService            : CommonsService,
        private _jwtHelperService          : JwtHelperService,
        private _configuracionService      : ConfiguracionService,
        private _reportesBackgroundService : ReportesBackgroundService,
        private _datosParametricosService  : DatosParametricosValidacionService,
        private _documentosRecibidosService: DocumentosRecibidosService,
    ) {
        super();
        this.registros         = [];
        this.parameters        = new Parameters();
        this.aclsUsuario       = this._auth.getAcls();
        this.usuario           = this._jwtHelperService.decodeToken();
        this._grupo_trabajo    = this.usuario.grupos_trabajo.singular;
        this.trackingRecepcionInterface = this;

        this.init();
    }

    private init() {
        this.initDataSort('cdo_fecha');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.form = this.fb.group({
            ofe_id                           : this.requerido(),
            cdo_fecha_validacion_dian_desde  : [''],
            cdo_fecha_validacion_dian_hasta  : [''],
            cdo_fecha_desde                  : this.requerido(),
            cdo_fecha_hasta                  : this.requerido(),
            pro_id                           : [''],
            cdo_origen                       : [''],
            cdo_consecutivo                  : [''],
            cdo_clasificacion                : [''],
            forma_pago                       : [''],
            estado_eventos_dian              : [''],
            resEventosDian                   : [''],
            estado_validacion                : [''],
            campo_validacion                 : [''],
            valor_campo_validacion           : [''],
            estado_acuse_recibo              : [''],
            estado_recibo_bien               : [''],
            estado_dian                      : [''],
            acuse_recibo                     : [''],
            recibo_bien_servicio             : [''],
            cdo_lote                         : [''],
            cdo_cufe                         : [''],
            rfa_prefijo                      : [''],
            cdo_resultado_ws_crt             : [''],
            transmision_erp                  : [''],
            transmision_opencomex            : [''],
            filtro_grupos_trabajo            : [''],
            filtro_grupos_trabajo_usuario    : [''],
            cdo_usuario_responsable_recibidos: [''],
            estado                           : ['']
        });

        this.ofe_id                            = this.form.controls['ofe_id'];
        this.cdo_fecha_validacion_dian_desde   = this.form.controls['cdo_fecha_validacion_dian_desde'];
        this.cdo_fecha_validacion_dian_hasta   = this.form.controls['cdo_fecha_validacion_dian_hasta'];
        this.cdo_fecha_desde                   = this.form.controls['cdo_fecha_desde'];
        this.cdo_fecha_hasta                   = this.form.controls['cdo_fecha_hasta'];
        this.pro_id                            = this.form.controls['pro_id'];
        this.cdo_origen                        = this.form.controls['cdo_origen'];
        this.cdo_clasificacion                 = this.form.controls['cdo_clasificacion'];
        this.forma_pago                        = this.form.controls['forma_pago'];
        this.estado_eventos_dian               = this.form.controls['estado_eventos_dian'];
        this.resEventosDian                    = this.form.controls['resEventosDian'];
        this.estado_validacion                 = this.form.controls['estado_validacion'];
        this.campo_validacion                  = this.form.controls['campo_validacion'];
        this.valor_campo_validacion            = this.form.controls['valor_campo_validacion'];
        this.estado_acuse_recibo               = this.form.controls['estado_acuse_recibo'];
        this.estado_recibo_bien                = this.form.controls['estado_recibo_bien'];
        this.estado_dian                       = this.form.controls['estado_dian'];
        this.acuse_recibo                      = this.form.controls['acuse_recibo'];
        this.recibo_bien_servicio              = this.form.controls['recibo_bien_servicio'];
        this.cdo_lote                          = this.form.controls['cdo_lote'];
        this.cdo_cufe                          = this.form.controls['cdo_cufe'];
        this.rfa_prefijo                       = this.form.controls['rfa_prefijo'];
        this.cdo_consecutivo                   = this.form.controls['cdo_consecutivo'];
        this.transmision_erp                   = this.form.controls['transmision_erp'];
        this.transmision_opencomex             = this.form.controls['transmision_opencomex'];
        this.filtro_grupos_trabajo             = this.form.controls['filtro_grupos_trabajo'];
        this.filtro_grupos_trabajo_usuario     = this.form.controls['filtro_grupos_trabajo_usuario'];
        this.cdo_usuario_responsable_recibidos = this.form.controls['cdo_usuario_responsable_recibidos'];
        this.estado                            = this.form.controls['estado'];
    }

    /**
     * Crea un JSON con los parámetros de búsqueda.
     *
     */
    public getSearchParametersObject(excel: boolean = false) {
        this.parameters.length         = this.length;
        this.parameters.columnaOrden   = this.columnaOrden;
        this.parameters.ordenDireccion = this.ordenDireccion;

        const fecha_envio_desde = this.cdo_fecha_validacion_dian_desde && this.cdo_fecha_validacion_dian_desde.value !== null && this.cdo_fecha_validacion_dian_desde.value !== '' ? String(moment(this.cdo_fecha_validacion_dian_desde.value).format('YYYY-MM-DD')) : '';

        const fecha_envio_hasta = this.cdo_fecha_validacion_dian_hasta && this.cdo_fecha_validacion_dian_hasta.value !== null && this.cdo_fecha_validacion_dian_hasta.value !== '' ? String(moment(this.cdo_fecha_validacion_dian_hasta.value).format('YYYY-MM-DD')) : '';

        const fecha_desde = this.cdo_fecha_desde && this.cdo_fecha_desde.value !== '' && this.cdo_fecha_desde.value != undefined ? String(moment(this.cdo_fecha_desde.value).format('YYYY-MM-DD')) : '';

        const fecha_hasta = this.cdo_fecha_hasta && this.cdo_fecha_hasta.value !== '' && this.cdo_fecha_hasta.value != undefined ? String(moment(this.cdo_fecha_hasta.value).format('YYYY-MM-DD')) : '';

        if(fecha_envio_desde)
            this.parameters.cdo_fecha_validacion_dian_desde = fecha_envio_desde;
        else
            delete this.parameters.cdo_fecha_validacion_dian_desde;

        if(fecha_envio_hasta)
            this.parameters.cdo_fecha_validacion_dian_hasta = fecha_envio_hasta;
        else
            delete this.parameters.cdo_fecha_validacion_dian_hasta;

        if (fecha_desde)
            this.parameters.cdo_fecha_desde = fecha_desde;
        else
            delete this.parameters.cdo_fecha_desde;

        if (fecha_hasta)
            this.parameters.cdo_fecha_hasta = fecha_hasta;
        else
            delete this.parameters.cdo_fecha_hasta;

        if (this.pro_id && this.pro_id.value && this.pro_id.value.length > 0)
            this.parameters.pro_id = this.pro_id.value;
        else
            delete this.parameters.pro_id;

        if (this.ofe_id && this.ofe_id.value)
            this.parameters.ofe_id = this.ofe_id.value;

        if (this.cdo_origen && this.cdo_origen.value)
            this.parameters.cdo_origen = this.cdo_origen.value;
        else
            delete this.parameters.cdo_origen;

        if (this.cdo_consecutivo && this.cdo_consecutivo.value.trim())
            this.parameters.cdo_consecutivo = this.cdo_consecutivo.value;
        else
            delete this.parameters.cdo_consecutivo;

        if (this.cdo_clasificacion && this.cdo_clasificacion.value)
            this.parameters.cdo_clasificacion = this.cdo_clasificacion.value;
        else
            delete this.parameters.cdo_clasificacion;

        if (this.forma_pago && this.forma_pago.value)
            this.parameters.forma_pago = this.forma_pago.value;
        else
            delete this.parameters.forma_pago;

        if(this.estado_eventos_dian && this.estado_eventos_dian.value.length > 0)
            this.parameters.estado_eventos_dian = this.estado_eventos_dian.value;
        else 
            delete this.parameters.estado_eventos_dian;

        if(this.resEventosDian && this.resEventosDian.value)
            this.parameters.resultado_eventos_dian = this.resEventosDian.value;
        else 
            delete this.parameters.resultado_eventos_dian;

        if(this.estado_validacion && this.estado_validacion.value && this.estado_validacion.value.length > 0)
            this.parameters.estado_validacion = this.estado_validacion.value;
        else 
            delete this.parameters.estado_validacion; 

        if(this.campo_validacion && this.campo_validacion.value)
            this.parameters.campo_validacion = this.campo_validacion.value;
        else 
            delete this.parameters.campo_validacion; 

        if(this.valor_campo_validacion && this.valor_campo_validacion.value)
            this.parameters.valor_campo_validacion = this.valor_campo_validacion.value;
        else 
            delete this.parameters.valor_campo_validacion; 

        if(this.estado_acuse_recibo && this.estado_acuse_recibo.value)
            this.parameters.estado_acuse_recibo = this.estado_acuse_recibo.value;
        else 
            delete this.parameters.estado_acuse_recibo; 

        if(this.estado_recibo_bien && this.estado_recibo_bien.value)
            this.parameters.estado_recibo_bien = this.estado_recibo_bien.value;
        else 
            delete this.parameters.estado_recibo_bien; 

        if(this.estado_dian && this.estado_dian.value && this.estado_dian.value.length > 0)
            this.parameters.estado_dian = this.estado_dian.value;
        else 
            delete this.parameters.estado_dian; 

        if(this.acuse_recibo && this.acuse_recibo.value)
            this.parameters.acuse_recibo = this.acuse_recibo.value;
        else 
            delete this.parameters.acuse_recibo; 

        if(this.recibo_bien_servicio && this.recibo_bien_servicio.value)
            this.parameters.recibo_bien_servicio = this.recibo_bien_servicio.value;
        else 
            delete this.parameters.recibo_bien_servicio; 

        if (this.cdo_lote && this.cdo_lote.value)
            this.parameters.cdo_lote = this.cdo_lote.value;
        else
            delete this.parameters.cdo_lote;

        if (this.cdo_cufe && this.cdo_cufe.value)
            this.parameters.cdo_cufe = this.cdo_cufe.value;
        else
            delete this.parameters.cdo_cufe;

        if (this.rfa_prefijo && this.rfa_prefijo.value.trim())
            this.parameters.rfa_prefijo = this.rfa_prefijo.value;
        else
            delete this.parameters.rfa_prefijo;

        if (excel)
            this.parameters.excel = true;
        else
            delete this.parameters.excel;

        if(this.transmision_erp && this.transmision_erp.value && this.transmision_erp.value.length > 0)
            this.parameters.transmision_erp = this.transmision_erp.value;
        else 
            delete this.parameters.transmision_erp; 

        if(this.transmision_opencomex && this.transmision_opencomex.value)
            this.parameters.transmision_opencomex = this.transmision_opencomex.value;
        else 
            delete this.parameters.transmision_opencomex; 

        if(this.filtro_grupos_trabajo && this.filtro_grupos_trabajo.value && this.mostrarFiltroGruposTrabajo)
            this.parameters.filtro_grupos_trabajo = this.filtro_grupos_trabajo.value;
        else 
            delete this.parameters.filtro_grupos_trabajo; 

        if(this.filtro_grupos_trabajo_usuario && this.filtro_grupos_trabajo_usuario.value && this.mostrarFiltroGruposTrabajo)
            this.parameters.filtro_grupos_trabajo_usuario = this.filtro_grupos_trabajo_usuario.value;
        else 
            delete this.parameters.filtro_grupos_trabajo_usuario; 

        if (this.cdo_usuario_responsable_recibidos && this.cdo_usuario_responsable_recibidos.value)
            this.parameters.cdo_usuario_responsable_recibidos = this.cdo_usuario_responsable_recibidos.value;
        else
            delete this.parameters.cdo_usuario_responsable_recibidos;

        if (this.estado && this.estado.value)
            this.parameters.estado = this.estado.value;
        else
            delete this.parameters.estado;

        if (this.paginador === 'anterior' && this.linkAnterior)
            this.parameters.pag_anterior = this.linkAnterior;
        else
            delete this.parameters.pag_anterior;

        if (this.paginador === 'siguiente' && this.linkSiguiente)
            this.parameters.pag_siguiente = this.linkSiguiente;
        else
            delete this.parameters.pag_siguiente;

        return this.parameters;
    }

    /**
     * Se encarga de traer la data de los documentos sin envío.
     *
     */
    public loadDocumentosRecibidos(): void {
        this.loading(true);
        this.visorEcm = (this.estadoVisorEcm) ? true : false;
        const parameters = this.getSearchParametersObject();
        this._documentosRecibidosService.listar(parameters).subscribe({
            next: res => {
                this.loading(false);
                this.registros    = [];
                this.linkAnterior  = res.pag_anterior ? res.pag_anterior : '';
                this.linkSiguiente = res.pag_siguiente ? res.pag_siguiente : '';
                
                res.data.forEach(reg => {
                    let moneda = reg.get_parametros_moneda ? reg.get_parametros_moneda.mon_codigo : 'COP';

                    let grupoTrabajo = '';
                    if(reg.get_grupo_trabajo) { // El documento esta asignado directamente a un grupo de trabajo
                        grupoTrabajo = reg.get_grupo_trabajo.gtr_codigo + ' - ' + reg.get_grupo_trabajo.gtr_nombre;
                    } else { // Se verifica si el proveedor está relacionado con un solo grupo de trabajo para mostrar que el documento esta asignado con ese grupo
                        if(reg.get_configuracion_proveedor.get_proveedor_grupos_trabajo && reg.get_configuracion_proveedor.get_proveedor_grupos_trabajo.length === 1)
                            grupoTrabajo = reg.get_configuracion_proveedor.get_proveedor_grupos_trabajo[0].get_grupo_trabajo.gtr_codigo + ' - ' + reg.get_configuracion_proveedor.get_proveedor_grupos_trabajo[0].get_grupo_trabajo.gtr_nombre;
                    }

                    this.registros.push(
                        {
                            'cdo_id'                           : reg.cdo_id,
                            'cdo_consecutivo'                  : reg.cdo_consecutivo,
                            'rfa_prefijo'                      : reg.rfa_prefijo ? reg.rfa_prefijo : '',
                            'cdo_lote'                         : reg.cdo_lote,
                            'ofe_id'                           : reg.ofe_id,
                            'ofe_identificacion'               : reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion,
                            'ofe_recepcion_fnc_activo'         : reg.get_configuracion_obligado_facturar_electronicamente.ofe_recepcion_fnc_activo,
                            'pro_id'                           : reg.pro_id,
                            'pro_razon_social'                 : reg.get_configuracion_proveedor.nombre_completo,
                            'pro_identificacion'               : reg.get_configuracion_proveedor.pro_identificacion,
                            'cdo_clasificacion'                : reg.cdo_clasificacion,
                            'cdo_cufe'                         : reg.cdo_cufe,
                            'cdo_fecha_validacion_dian'        : reg.cdo_fecha_validacion_dian,
                            'documento'                        : (reg.rfa_prefijo ? reg.rfa_prefijo : '') + ' ' + reg.cdo_consecutivo,
                            'fecha'                            : reg.cdo_fecha + ' ' + (reg.cdo_hora ? reg.cdo_hora : ''),
                            'moneda'                           : moneda,
                            'valor_a_pagar'                    : reg.cdo_valor_a_pagar,
                            'cdo_origen'                       : reg.cdo_origen,
                            'grupo_trabajo'                    : grupoTrabajo ?? '',
                            'usuario_responsable_recibidos'    : reg.get_usuario_responsable_recibidos?.usu_nombre ?? '',
                            'tde_codigo'                       : reg.get_tipo_documento_electronico ? reg.get_tipo_documento_electronico.tde_codigo : '',
                            'estado'                           : reg.estado,
                            'fecha_creacion'                   : reg.fecha_creacion,
                            'cdo_rdi'                          : reg.cdo_rdi,
                            'cdo_rdi_informacion_adicional'    : reg.cdo_rdi_informacion_adicional,
                            'cdo_estado_dian'                  : reg.cdo_estado_dian,
                            'cdo_estado_notificacion'          : reg.cdo_notificacion_evento_dian,
                            'cdo_estado_notificacion_resultado': reg.cdo_notificacion_evento_dian_resultado,
                            'cdo_estado_eventos_dian'          : reg.cdo_estado_eventos_dian,
                            'cdo_estado_eventos_dian_resultado': reg.cdo_estado_eventos_dian_resultado,
                            'cdo_documentos_anexos'            : reg.cdo_documentos_anexos,
                            'cdo_estado_transmisionerp'        : reg.cdo_transmision_erp,
                            'cdo_estado_transmision_opencomex' : reg.cdo_transmision_opencomex,
                            'cdo_validacion'                   : reg.cdo_validacion
                        }
                    );
                });

                this.loadingIndicator = false;
                this.totalElements    = res.data.length;
                this.totalShow        = this.length;

                if (res.data.length > 0) {
                    this.existeConsulta = true;
                } else {
                    this.existeConsulta = false;
                }
            },
            error: error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Documentos Recibidos', '0k, entiendo', 'btn btn-danger');
            }
        });
    }

    ngOnInit() {
        this.pro_id.disable();

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionCambiarEstadoDocumentos')) {
            this.accionesBloque.push(
                { id: 'cambio_estado_doc', nombre: 'Cambio Estado Documento' }
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionConsultarEstadoDianDocumentos')) {
            this.accionesBloque.push(
                {id: 'consultar_estado_dian', nombre: 'Consultar Estado DIAN'}
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionAcuseReciboDocumentos')) {
            this.accionesBloque.push(
                {id: 'acuse_recibo', nombre: 'Acuse de Recibo'}
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionReciboBien')) {
            this.accionesBloque.push(
                {id: 'recibo_del_bien', nombre: 'Recibo del bien y/o prestación del servicio'}
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionAceptacionExpresa')) {
            this.accionesBloque.push(
                {id: 'aceptacion_documento', nombre: 'Aceptación Expresa'}
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionReclamoDocumentos')) {
            this.accionesBloque.push(
                {id: 'rechazo_documento', nombre: 'Reclamo (Rechazo)'}
            );
        }

        this.cargarOfes();
        this.setearControlUsuarios();
    }

    /**
     * Carga los OFEs en el select de emisores.
     *
     */
    private cargarOfes() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false').subscribe({
            next: result => {
                this.loading(false);
                this.ofes = [];
                result.data.ofes.forEach(ofe => {
                    if(ofe.ofe_recepcion === 'SI') {
                        ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                        this.ofes.push(ofe);
                    }
                });
                this.arrFormaPago = result.data.formas_pago;
                this.arrActoresRadian = result.data.actores_radian;
            },
            error: error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los OFEs', 'Ok', 'btn btn-danger');
            }
        });
    }

    /**
     * Recarga el datatable con la información filtrada.
     *
     */
    searchDocumentos(values): void {
        this.loading(true);
        if (this.form.valid) {
            this.onPage();
            this.documentosTrackingRecepcion.tracking.offset = 0;
        } else {
            this.loading(false);
        }
    }

    /**
     * Permite descargar XML, PDF y Acuse de recibo de documentos.
     *
     */
    downloadDocs(tipos) {
        let ids = '';
        let tiposDescargas = tipos ? tipos.join(',') : '';
        let ofeId = this.ofe_id.value;
        if (this.selected.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Descarga de Documentos', 'Ok, entiendo', 'btn btn-warning');
        } else if (tiposDescargas === '') {
            this.showError('<h3>Debe seleccionar al menos un Tipo de Documento a descargar</h3>', 'warning', 'Descarga de Documentos', 'Ok, entiendo', 'btn btn-warning');
        } else {
            this.loading(true);
            this.selected.forEach(reg => {
                // ids.push(reg.cdo_id);
                ids += reg.cdo_id + ',';
            });
            ids = ids.slice(0, -1);
            this._documentosRecibidosService.descargarDocumentos(tiposDescargas, ids, ofeId).subscribe({
                next: response => {
                    this.loading(false);
                },
                error: (error) => {
                    this.loading(false);
                }
            });
        }
    }

    /**
     * Realiza una búsqueda de los usuarios con tipo validador dado el valor a buscar.
     *
     * @private
     * @memberof ValidacionDocumentosComponent
     */
    private setearControlUsuarios() {
        const vacioUsuarios: Usuario[] = [];
        this.usuarios$ = concat(
            of(vacioUsuarios),
            this.usuariosInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._configuracionService.searchUsuariosGestorValidador('gestor', term).pipe(
                    catchError(() => of(vacioUsuarios)),
                    tap(() => this.loading(false))
                ))
            )
        );
    }

    /**
     * Limpia la lista de los usuarios obtenidos en el autocompletar del campo usu_identificacion_nombre.
     *
     * @memberof ValidacionDocumentosComponent
     */
    clearUsuario(): void {
        this.selectedUsuId = null;

        if(this.selectUsuarios)
            this.selectUsuarios.items = [];

        this.usuariosInput$.next('');
    }

    /**
     * Permite reenviar notificaciones de eventos.
     *
     * @param {string} tipo Tipo de evento para el cual se reenviará la notificación
     */
    reenviarNotificacion(tipo) {
        if (this.selected.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Reenvío de Notificación', 'Ok, entiendo', 'btn btn-warning');
        } else if (!tipo) {
            this.showError('<h3>Debe indicar el evento para el cual realizará el reenvío de la notificación</h3>', 'warning', 'Reenvío de Notificación', 'Ok, entiendo', 'btn btn-warning');
        } else {
            this.loading(true);
            let arrDocumentos: any = [];
            this.selected.forEach(reg => {
                arrDocumentos.push(
                    {
                        ofe_identificacion: reg.ofe_identificacion,
                        pro_identificacion: reg.pro_identificacion,
                        rfa_prefijo       : reg.rfa_prefijo,
                        cdo_consecutivo   : reg.cdo_consecutivo,
                        tde_codigo        : reg.tde_codigo,
                        cdo_cufe          : reg.cdo_cufe
                    }
                );
            });

            let json = {
                evento: tipo,
                documentos: arrDocumentos
            }
            this._documentosRecibidosService.reenvioNotificacion(json).subscribe({
                next: response => {
                    this.loading(false);
                    let mensajeAdicional = '';
                    if(response.resultado) {
                        if (Array.isArray(response.resultado) && response.resultado.length > 0) {
                            response.resultado.forEach(strResultado => {
                                mensajeAdicional += '<li>' + strResultado + '</li>';
                            });
                        } else if (typeof response.resultado === 'string')
                            mensajeAdicional = '<li>' + response.resultado + '</li>';
                        else if (typeof response.resultado === 'undefined'){
                            mensajeAdicional = '';
                        }
                    }
                    this.showSuccess(response.message + (mensajeAdicional ? '<span style="text-align:left; font-weight: bold;"><ul>' + mensajeAdicional + '</ul></span>' : ''), 'success', 'Reenviar Notificacion', 'Ok', 'btn btn-success');
                },
                error: error => {
                    this.loading(false);
                    this.mostrarErrores(error, 'Error al reenviar notificación');
                }
            });
        }
    }

    /**
     * Determina si debe mostrarse en el control para estado de Aceptado por el OFE.
     *
     * @param data
     */
    checkEstadoAceptado(data) {
        if(data.estado_dian === 'ACEPTACION')
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Aceptado Tácitamente.
     *
     * @param data
     */
    checkEstadoAceptadoTacitamente(data) {
        if(data.estado_dian === 'ACEPTACIONT')
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Rechazado por el OFE.
     *
     * @param data
     */
    checkEstadoRechazado(data) {
        if(data.estado_dian === 'RECHAZO')
            return true;

        return false;
    }

    /**
     * Determina si debe mostrarse en el control para estado de Rechazado por el OFE.
     *
     * @param data
     */

    /**
     * Verifica el último estado de validación para el documento.
     *
     * @param {*} data Información del documento
     * @return {boolean} 
     * @memberof DocumentosRecibidosComponent
     */
    checkEstadoValidacion(data) {
        if(
            !data.ultimo_estado_validacion ||
            (
                data.ultimo_estado_validacion && data.ultimo_estado_validacion.est_estado === 'VALIDACION' && (
                    data.ultimo_estado_validacion.est_resultado === 'RECHAZADO' || data.ultimo_estado_validacion.est_resultado === 'ENPROCESO'
                )
            )
        )
            return true;

        return false;
    }

    /**
     * Responde a las diferentes acciones del combo de Acciones en Bloque.
     *
     */
    accionesEnBloque(selectedOption) {
        let registros      = {lote: '', documentos: []};
        let docs           = '';
        let ids            = '';
        let documentos     = '';
        let mensaje        = '';
        let that           = this;
        let ofeId          = this.ofe_id.value ? this.ofe_id.value : null;
        let permitirAccion = true;
        let tipoDs         = false;
        let estadoValidacionEnProcesoPendiente: any;

        let msgAccionNoPermitida = '<h3>Verifique los documentos seleccionados, uno o varios de ellos ya fueron aceptados, aceptados tácitamente o rechazados.<br><br>Debe seleccionar documentos que <strong>NO</strong> cuenten con ninguno de esos tres estados.</h3>';
        let msgAccionNoPermitidaDs = '<h3>Acción no permitida sobre Documentos Soporte</h3>';

        if (this.selected.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Acciones en Bloque', 'Ok, entiendo', 'btn btn-warning');
        } else {
            switch (selectedOption) {
                case 'cambio_estado_doc':
                    let docsCambioEstado = [];
                    this.selected.forEach(reg => {
                        ids += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsCambioEstado.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        // Si el documento ha sido aceptado, aceptado tácitamente o rechazado no se debe permitir la acción en bloque
                        if(this.checkEstadoAceptado(reg) || this.checkEstadoAceptadoTacitamente(reg) || this.checkEstadoRechazado(reg))
                            permitirAccion = false;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Cambio Estado Documentos', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (!permitirAccion) {
                        this.showError(msgAccionNoPermitida, 'warning', 'Cambio Estado Documentos', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Está seguro de cambiar el estado a los siguientes documentos seleccionados?:</span><br><br><ul>';
                        docsCambioEstado.forEach((doc) => {
                            mensaje += "<li style='text-align:left;'>" + doc + "</li>";
                        });
                        mensaje += '</ul>';
                    }
                    swal({
                        html: mensaje,
                        type: 'warning',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar',
                        confirmButtonClass: 'btn btn-success',
                        cancelButtonClass: 'btn btn-danger',
                        confirmButtonText: 'Aceptar',
                        buttonsStyling: false,
                        allowOutsideClick: false,
                    }).then(function (result) {
                        if (result.value) {
                            that.loading(true);
                            that._documentosRecibidosService.cambiarEstadoDocumentosRecibidos(registros.documentos.join(',')).subscribe({
                                next: response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    let mensajeFinal = '<ul>';
                                    response.message != '' ? mensajeFinal += '<li style="text-align:left;">' + response.message + '</li>' : mensajeFinal += '';
                                    let documentosErrores = '';
                                    response.errors.forEach((error) => {
                                        documentosErrores += '<li style="text-align:left;">' + error + '</li>';
                                    });
                                    mensajeFinal += documentosErrores + '</ul>';
                                    that.showSuccess(mensajeFinal, 'success', 'Cambio Estado Documentos', 'Ok', 'btn btn-success');
                                },
                                error: error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al cambiar el estado de los Documentos');
                                }
                            });
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;

                case 'consultar_estado_dian':
                    let docsConsultarestadoDian = [];
                    this.selected.forEach(reg => {
                        ids += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsConsultarestadoDian.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Consultar Estado Dian', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Consultar el estado DIAN para los siguientes documentos?:</span><br><br><ul>';
                        docsConsultarestadoDian.forEach((doc) => {
                            mensaje += "<li style='text-align:left;'>" + doc + "</li>";
                        });
                        mensaje += '</ul>';
                    }
                    swal({
                        html: mensaje,
                        type: 'warning',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar',
                        confirmButtonClass: 'btn btn-success',
                        cancelButtonClass: 'btn btn-danger',
                        confirmButtonText: 'Aceptar',
                        buttonsStyling: false,
                        allowOutsideClick: false,
                    }).then(function (result) {
                        if (result.value) {
                            that.loading(true);
                            that._documentosRecibidosService.agendarConsultaEstadoDianDocumentosRecibidos(that.ofe_id.value, registros.documentos.join(',')).subscribe({
                                next: response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Consultar Estado Dian', 'Ok', 'btn btn-success');
                                },
                                error: error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al intentar agendar la consulta de estados en la DIAN');
                                }
                            });
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;

                case 'acuse_recibo':
                    let docsAcuseRecibo = [];
                    this.selected.forEach(reg => {
                        ids += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsAcuseRecibo.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        // Si el documento ha sido aceptado, aceptado tácitamente o rechazado no se debe permitir la acción en bloque
                        if(this.checkEstadoAceptado(reg) || this.checkEstadoAceptadoTacitamente(reg) || this.checkEstadoRechazado(reg))
                            permitirAccion = false;

                        if (reg.cdo_clasificacion == 'DS' || reg.cdo_clasificacion == 'DS_NC')
                            tipoDs = true;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Acuse de Recibo', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (tipoDs) {
                        this.showError(msgAccionNoPermitidaDs, 'warning', 'Documento Soporte', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (!permitirAccion) {
                        this.showError(msgAccionNoPermitida, 'warning', 'Acuse de Recibo', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        let dataDocumentos = {
                            'ofe_id': that.ofe_id.value,
                            'cdo_ids': registros.documentos.join(','),
                            'documentos_procesar': docsAcuseRecibo
                        }
                        this.openModalEventosDianDocumentos(selectedOption, dataDocumentos);
                    }
                    break;

                case 'recibo_del_bien':
                    let docsReciboBien = [];
                    this.selected.forEach(reg => {
                        ids += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsReciboBien.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        // Si el documento ha sido aceptado, aceptado tácitamente o rechazado no se debe permitir la acción en bloque
                        if(this.checkEstadoAceptado(reg) || this.checkEstadoAceptadoTacitamente(reg) || this.checkEstadoRechazado(reg))
                            permitirAccion = false;

                        if(estadoValidacionEnProcesoPendiente === undefined)
                            estadoValidacionEnProcesoPendiente = reg.estado_validacion_en_proceso_pendiente;

                        if (reg.cdo_clasificacion == 'DS' || reg.cdo_clasificacion == 'DS_NC')
                            tipoDs = true;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Recibo del bien y/o prestación del servicio', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (tipoDs) {
                        this.showError(msgAccionNoPermitidaDs, 'warning', 'Documento Soporte', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (!permitirAccion) {
                        this.showError(msgAccionNoPermitida, 'warning', 'Recibo del bien y/o prestación del servicio', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        let dataDocumentos = {
                            'ofe_id'                                : that.ofe_id.value,
                            'cdo_ids'                               : registros.documentos.join(','),
                            'documentos_procesar'                   : docsReciboBien,
                            'estado_validacion_en_proceso_pendiente': estadoValidacionEnProcesoPendiente
                        }
                        this.openModalEventosDianDocumentos(selectedOption, dataDocumentos);
                    }
                    break;
                    
                case 'aceptacion_documento':
                    let docsAceptacion = [];
                    this.selected.forEach(reg => {
                        ids += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsAceptacion.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        // Si el documento ha sido aceptado, aceptado tácitamente o rechazado no se debe permitir la acción en bloque
                        if(this.checkEstadoAceptado(reg) || this.checkEstadoAceptadoTacitamente(reg) || this.checkEstadoRechazado(reg))
                            permitirAccion = false;

                        if (reg.cdo_clasificacion == 'DS' || reg.cdo_clasificacion == 'DS_NC')
                            tipoDs = true;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Aceptación Documento', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (tipoDs) {
                        this.showError(msgAccionNoPermitidaDs, 'warning', 'Documento Soporte', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (!permitirAccion) {
                        this.showError(msgAccionNoPermitida, 'warning', 'Aceptación Expresa', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        let dataDocumentos = {
                            'ofe_id': that.ofe_id.value,
                            'cdo_ids': registros.documentos.join(','),
                            'documentos_procesar': docsAceptacion
                        }
                        this.openModalEventosDianDocumentos(selectedOption, dataDocumentos);
                    }
                    break;

                case 'rechazo_documento':
                    let docsRechazo = [];
                    this.selected.forEach(reg => {
                        ids += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsRechazo.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        // Si el documento ha sido aceptado, aceptado tácitamente o rechazado no se debe permitir la acción en bloque
                        if(this.checkEstadoAceptado(reg) || this.checkEstadoAceptadoTacitamente(reg) || this.checkEstadoRechazado(reg))
                            permitirAccion = false;

                        if (reg.cdo_clasificacion == 'DS' || reg.cdo_clasificacion == 'DS_NC')
                            tipoDs = true;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Rechazo de Documentos', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (tipoDs) {
                        this.showError(msgAccionNoPermitidaDs, 'warning', 'Documento Soporte', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (!permitirAccion) {
                        this.showError(msgAccionNoPermitida, 'warning', 'Rechazo de Documentos', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        let dataDocumentos = {
                            'ofe_id': that.ofe_id.value,
                            'cdo_ids': registros.documentos.join(','),
                            'documentos_procesar': docsRechazo
                        }
                        this.openModalEventosDianDocumentos(selectedOption, dataDocumentos);
                    }
                    break;

                case 'transmitir_erp':
                    let docsTransmitir = [];
                    this.selected.forEach(reg => {
                        docsTransmitir.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        if (reg.cdo_clasificacion == 'DS' || reg.cdo_clasificacion == 'DS_NC')
                            tipoDs = true;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Transmitir ERP', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (tipoDs) {
                        this.showError(msgAccionNoPermitidaDs, 'warning', 'Documento Soporte', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Realizar la transmisión a ERP de los siguientes documentos?:</span><br><br><ul>';
                        docsTransmitir.forEach((doc) => {
                            mensaje += "<li style='text-align:left;'>" + doc + "</li>";
                        });
                        mensaje += '</ul>';
                    }
                    swal({
                        html: mensaje,
                        type: 'warning',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar',
                        confirmButtonClass: 'btn btn-success',
                        cancelButtonClass: 'btn btn-danger',
                        confirmButtonText: 'Aceptar',
                        buttonsStyling: false,
                        allowOutsideClick: false,
                    }).then(function (result) {
                        if (result.value) {
                            that.loading(true);
                            that._documentosRecibidosService.transmitirErp(that.ofe_id.value, registros.documentos.join(',')).subscribe({
                                next: response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Transmitir a ERP', 'Ok', 'btn btn-success');
                                },
                                error: error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al intentar la Transmisión a ERP');
                                }
                            });
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;

                case 'transmitir_opencomex':
                    let docsTransmitirOpencomex = [];
                    this.selected.forEach(reg => {
                        docsTransmitirOpencomex.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        if (reg.cdo_clasificacion == 'DS' || reg.cdo_clasificacion == 'DS_NC')
                            tipoDs = true;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Transmitir a openComex', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (tipoDs) {
                        this.showError(msgAccionNoPermitidaDs, 'warning', 'Documento Soporte', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Realizar la transmisión a openComex de los siguientes documentos?:</span><br><br><ul>';
                        docsTransmitirOpencomex.forEach((doc) => {
                            mensaje += "<li style='text-align:left;'>" + doc + "</li>";
                        });
                        mensaje += '</ul>';
                    }
                    swal({
                        html: mensaje,
                        type: 'warning',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar',
                        confirmButtonClass: 'btn btn-success',
                        cancelButtonClass: 'btn btn-danger',
                        confirmButtonText: 'Aceptar',
                        buttonsStyling: false,
                        allowOutsideClick: false,
                    }).then(function (result) {
                        if (result.value) {
                            that.loading(true);
                            that._documentosRecibidosService.transmitirOpencomex(registros.documentos.join(',')).subscribe({
                                next: response => {
                                    that.recargarLista();
                                    that.loading(false);

                                    let mensajeAdicional = '';
                                    if(response.resultado) {
                                        if (Array.isArray(response.resultado) && response.resultado.length > 0) {
                                            response.resultado.forEach(strResultado => {
                                                mensajeAdicional += '<li>' + strResultado + '</li>';
                                            });
                                        } else if (typeof response.resultado === 'string')
                                            mensajeAdicional = '<li>' + response.resultado + '</li>';
                                        else if (typeof response.resultado === 'undefined'){
                                            mensajeAdicional = '';
                                        }
                                    }
                                    
                                    that.showSuccess(response.message + (mensajeAdicional ? '<span style="text-align:left; font-weight: bold;"><ul>' + mensajeAdicional + '</ul></span>' : ''), 'success', 'Transmitir a openComex', 'Ok', 'btn btn-success');
                                },
                                error: error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al intentar la Transmisión a openComex');
                                }
                            });
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;

                case 'asignar_grupo_trabajo':
                    let docsProId: number;
                    let diferentesProveedores = false;
                    let docsAsignarGrupoTrabajo = [];
                    this.selected.forEach(reg => {
                        if(docsProId === undefined)
                            docsProId = reg.pro_id;
                        else
                            if(docsProId !== reg.pro_id)
                                diferentesProveedores = true;

                        docsAsignarGrupoTrabajo.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        if (reg.cdo_clasificacion == 'DS' || reg.cdo_clasificacion == 'DS_NC')
                            tipoDs = true;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Asignar ' + this._grupo_trabajo, 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (tipoDs) {
                        this.showError(msgAccionNoPermitidaDs, 'warning', 'Documento Soporte', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        if(diferentesProveedores) {
                            this.showError('<h3>Los documentos seleccionados tienen diferentes proveedores, esta acción es permitida para documentos con el mismo proveedor.</h3>', 'warning', 'Asignar ' + this._grupo_trabajo, 'Ok, entiendo', 'btn btn-warning');
                            return false;
                        }

                        let dataDocumentos = {
                            '_grupo_trabajo': this._grupo_trabajo,
                            'cdo_ids': registros.documentos.join(','),
                            'pro_id': docsProId,
                            'documentos_asignar_grupo_trabajo': docsAsignarGrupoTrabajo
                        }
                        this.openModalAsignarGrupoTrabajoDocumentos(dataDocumentos);
                    }
                    break;

                case 'documento_validado':
                case 'datos_validacion':
                case 'enviar_a_validacion':
                    let docsNoProcesar      = [];
                    let docsDatosValidacion = [];
                    let tituloMensajes      = selectedOption == 'datos_validacion' ? 'Datos Validación' : (selectedOption == 'documento_validado' ? 'Documento Validado' : 'Enviar a Validación');

                    this.selected.forEach(reg => {
                        ids += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsDatosValidacion.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        // Debe verificar que cada documento no tenga estado VALIDACIÓN o que si tiene ese estado el resultado más reciente sea RECHAZADO o ENPROCESO
                        if(selectedOption != 'documento_validado' && !this.checkEstadoValidacion(reg)) {
                            if(permitirAccion)
                                permitirAccion = false;

                            docsNoProcesar.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo + (reg.ultimo_estado_validacion.est_resultado ? ' tiene estado VALIDACION ' + reg.ultimo_estado_validacion.est_resultado : ''));
                        // La acción de documento validado solo se permite para las NC, ND y documentos con origen NO-ELECTRONICO
                        } else if (
                            selectedOption == 'documento_validado' && !(
                                (
                                    reg.cdo_origen == 'NO-ELECTRONICO'
                                ) || (
                                    (reg.cdo_clasificacion == 'NC' || reg.cdo_clasificacion == 'ND')
                                )
                            )
                        ) {
                            if(permitirAccion)
                                permitirAccion = false;

                            docsNoProcesar.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo + ' - Solo se permite la acción para documentos NC, ND o NO-ELCTRONICO');
                        }

                        if(estadoValidacionEnProcesoPendiente === undefined)
                            estadoValidacionEnProcesoPendiente = reg.estado_validacion_en_proceso_pendiente;

                        if (reg.cdo_clasificacion == 'DS' || reg.cdo_clasificacion == 'DS_NC')
                            tipoDs = true;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', tituloMensajes, 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (tipoDs) {
                        this.showError(msgAccionNoPermitidaDs, 'warning', tituloMensajes, 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (!permitirAccion) {
                        this.showError('<h3>Los siguientes documentos no pueden ser procesados:<br>[' + (docsNoProcesar.join(', ')) + ']</h3>', 'warning', tituloMensajes, 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        let dataDocumentos = {
                            'ofe_id'                                       : that.ofe_id.value,
                            'cdo_ids'                                      : registros.documentos.join(','),
                            'documentos_procesar'                          : docsDatosValidacion,
                            'estado_validacion_en_proceso_pendiente'       : estadoValidacionEnProcesoPendiente
                        }

                        this.openModalEventosDianDocumentos(selectedOption, dataDocumentos);
                    }
                    break;

                case 'consulta_aceptacion_tacita':
                    let docsAceptacionTacita = [];
                    this.selected.forEach(reg => {
                        ids += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsAceptacionTacita.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);

                        // Si el documento ha sido aceptado, aceptado tácitamente o rechazado no se debe permitir la acción en bloque
                        if(this.checkEstadoAceptado(reg) || this.checkEstadoAceptadoTacitamente(reg) || this.checkEstadoRechazado(reg))
                            permitirAccion = false;

                        if (reg.cdo_clasificacion == 'DS' || reg.cdo_clasificacion == 'DS_NC')
                            tipoDs = true;
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Consulta Aceptación Tácita', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (tipoDs) {
                        this.showError(msgAccionNoPermitidaDs, 'warning', 'Documento Soporte', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else if (!permitirAccion) {
                        this.showError(msgAccionNoPermitida, 'warning', 'Consulta Aceptación Tácita', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Desea consultar el estado de Aceptación Tácita para los siguientes documentos seleccionados?:</span><br><br><ul>';
                        docsAceptacionTacita.forEach((doc) => {
                            mensaje += `<li style='text-align:left;'> ${doc} </li>`;
                        });
                        mensaje += '</ul>';
                    }

                    swal({
                        html: mensaje,
                        type: 'warning',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar',
                        confirmButtonClass: 'btn btn-success',
                        cancelButtonClass: 'btn btn-danger',
                        confirmButtonText: 'Aceptar',
                        buttonsStyling: false,
                        allowOutsideClick: false,
                    }).then(function (result) {
                        if (result.value) {
                            that.loading(true);
                            that._documentosRecibidosService.agendarAceptacionTacitaDocumentosRecibidos(that.ofe_id.value, registros.documentos.join(',')).subscribe({
                                next: response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Consulta Aceptación Tácita', 'Ok', 'btn btn-success');
                                },
                                error: error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al intentar agendar la Aceptación Tácita');
                                }
                            });
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;

                case 'enviar_documento_radian':
                    let docsEnviarRadian = [];
                    let docsPrefijoConsecutivo = [];
                    this.selected.forEach(reg => {
                        docsPrefijoConsecutivo.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        docsEnviarRadian.push({'act_identificacion' : reg.ofe_identificacion, 'rol_id': 2, 'cufe': reg.cdo_cufe});
                    });

                    if (docsEnviarRadian.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Enviar Documento a Radian', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Desea enviar a RADIAN los siguientes documentos?:</span><br><br><ul>';
                        docsPrefijoConsecutivo.forEach((doc) => {
                            mensaje += `<li style='text-align:left;'>${doc}</li>`;
                        });
                        mensaje += '</ul>';
                    }
                    swal({
                        html: mensaje,
                        type: 'warning',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar',
                        confirmButtonClass: 'btn btn-success',
                        cancelButtonClass: 'btn btn-danger',
                        confirmButtonText: 'Aceptar',
                        buttonsStyling: false,
                        allowOutsideClick: false,
                    }).then(function (result) {
                        if (result.value) {
                            that.loading(true);
                            let objData = {'data': docsEnviarRadian};
                            
                            that._radianService.agendarEstadoRadEdi(objData).subscribe({
                                next: response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Enviar a RADIAN', 'Ok', 'btn btn-success');
                                },
                                error: error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al intentar enviar a RADIAN');
                                }
                            });

                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;
                default:
                    break;
            }
        }
        this.selected = [];
        selectedOption = null;
    }

    /**
     * Apertura una ventana modal para eventos DIAN.
     *
     * @param selectedOption Acción en bloque seleccionada
     * @param data Objeto con la información de los documentos a rechazar
     */
    public openModalEventosDianDocumentos(selectedOption, data): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '500px';
        modalConfig.data = {
            documentos           : data,
            selectedOption       : selectedOption,
            parent               : this,
            ofeRecepcionFncActivo: this.ofeRecepcionFncActivo,
            ofeRecepcionFncConfiguracion: this.ofeRecepcionFncConfiguracion
        };
        this.modalRechazos = this.modal.open(ModalEventosDocumentosComponent, modalConfig);
    }

    /**
     * Apertura una ventana modal para rechazar documentos.
     *
     * @param data Objeto con la información de los documentos a los cuales se les asignará grupo de trabajo
     */
    public openModalAsignarGrupoTrabajoDocumentos(data): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '500px';
        modalConfig.data = {
            documentos: data,
            parent: this,
        };
        this.modalAsignarGruposTrabajo = this.modal.open(ModalAsignarGrupoTrabajoDocumentosComponent, modalConfig);
    }

    /**
     * Gestiona el evento de paginacion de la grid.
     *
     * @param $evt
     */
    public onPage(page: string = null) {
        this.paginador = page;
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
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'fecha':
            default:
                this.columnaOrden = 'cdo_fecha';
                break;
        }

        this.paginador      = '';
        this.ordenDireccion = $order;

        delete this.parameters.pag_anterior;
        delete this.parameters.pag_siguiente;

        this.recargarLista();
    }

    /**
     * Efectua la carga de datos.
     *
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadDocumentosRecibidos();
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
     * Gestiona la acción de los botones de opciones de un registro.
     *
     */
    onOptionItem(item: any, opcion: string) {
        switch (opcion) {
            case 'ver-documento':
                this.openModalDocumentosLista(item);
                break;
            default:
                break;
        }
    }

    /**
     * Gestiona la acción del botón de descarga de documentos
     *
     */
    onDescargarItems(selected: any[], tipos) {
        this.selected = selected;
        this.downloadDocs(tipos);
    }

    /**
     * Gestiona la acción del botón que permite reenviar la notificación de un evento
     *
     */
    onReenvioNotificacion(selected: any[], tipos) {
        this.selected = selected;
        this.reenviarNotificacion(tipos);
    }

    /**
     * Gestiona la acción del botón de ver un registro
     *
     */
    onDescargarExcel() {
        this.descargarExcel();
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
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     *
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Metodo Interface para agendar los reportes en background de documentos recibidos.
     *
     * @memberof DocumentosRecibidosComponent
     */
    onAgendarReporteBackground() {
        if (this.existeConsulta)
            this.agendarReporteBackground();
        else
            this.showError('<h3>Debe existir una consulta con registros listados</h3>', 'warning', 'Error al descargar excel', 'OK', 'btn btn-warning');
    }

    /**
     * Descarga los documentos enviados filtrados en un archivos de excel.
     *
     */
    descargarExcel() {
        if (this.existeConsulta) {
            this.loading(true);
            this._documentosRecibidosService.descargarExcel(this.getSearchParametersObject(true)).subscribe({
                next: response => {
                    this.loading(false);
                },
                error: (error) => {
                    this.loading(false);
                    this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar excel de Documentos Enviados', 'OK', 'btn btn-danger');
                }
            });
        } else {
            this.showError('<h3>Debe existir una consulta con registros listados</h3>', 'warning', 'Error al descargar excel', 'OK', 'btn btn-warning');
        }
    }

    /**
     * Apertura una ventana modal para ver documentos.
     *
     * @param usuario
     */
    public openModalDocumentosLista(item: any): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '800px';
        modalConfig.data = {
            item: item,
            parent: this
        };
        this.modalDocumentosLista = this.modal.open(ModalDocumentosListaComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un documento.
     *
     */
    public closeModalDocumentosLista(): void {
        if (this.modalDocumentosLista) {
            this.modalDocumentosLista.close();
            this.modalDocumentosLista = null;
        }
    }

    /**
     * Monitoriza cuando el valor del select de OFEs cambia para realizar acciones determinadas de acuerdo al OFE.
     * 
     * @param {object} ofe Objeto con la información del OFE seleccionado
     * @memberof DocumentosRecibidosComponent
     */
    ofeHasChanged(ofe) {
        this.registros = [];
        this.existeConsulta = false;
        this.transmision_erp.setValue('');
        this.transmision_opencomex.setValue('');
        
        if(
            ofe.ofe_recepcion === 'SI' &&
            ofe.ofe_recepcion_transmision_erp === 'SI' &&
            (
                this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionTransmitirErp')
            )
        ) {
            let existe = false;
            this.accionesBloque.forEach((valor: any, indice: number) => {
                if(valor.id && valor.id === 'transmitir_erp')
                    existe = true;
            });

            if(!existe) 
                this.accionesBloque.push(
                    {id: 'transmitir_erp', nombre: 'Transmitir a ERP'}
                );
        } else {
            this.accionesBloque.forEach((valor: any, indice: number) => {
                if(valor.id && valor.id === 'transmitir_erp')
                    this.accionesBloque.splice(indice, 1);
            });
        }

        this.estadoVisorEcm = this._openEcm.validarVisorEcm(ofe);

        if(ofe.ofe_recepcion === 'SI' && ofe.ofe_recepcion_transmision_erp === 'SI') {
            this.mostrarFiltroTransmisionErp = true;
        } else {
            this.mostrarFiltroTransmisionErp = false;
        }

        if(
            ofe.ofe_recepcion === 'SI' &&
            ofe.ofe_identificacion === this.identificacionAgenciaAduanasDhl &&
            (
                this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') ||
                this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionTransmitirOpencomex')
            )
        ) {
            let existe = false;
            this.accionesBloque.forEach((valor: any, indice: number) => {
                if(valor.id && valor.id === 'transmitir_opencomex')
                    existe = true;
            });

            if(!existe) 
                this.accionesBloque.push(
                    {id: 'transmitir_opencomex', nombre: 'Transmitir a openComex'}
                );
        } else {
            this.accionesBloque.forEach((valor: any, indice: number) => {
                if(valor.id && valor.id === 'transmitir_opencomex')
                    this.accionesBloque.splice(indice, 1);
            });
        }

        if(ofe.ofe_recepcion === 'SI' && ofe.ofe_identificacion === this.identificacionAgenciaAduanasDhl) {
            this.mostrarFiltroTransmisionOpencomex = true;
        } else {
            this.mostrarFiltroTransmisionOpencomex = false;
        }

        if(ofe.get_grupos_trabajo && ofe.get_grupos_trabajo.length > 0) {
            this.columns.push({name: this._grupo_trabajo, prop: 'grupo_trabajo', sorteable: false, width: '200'});
            this.columns.push({name: 'Responsable', prop: 'usuario_responsable_recibidos', sorteable: false, width: '200'});

            this.mostrarFiltroGruposTrabajo = false;
            if(ofe.ofe_recepcion_fnc_activo == 'SI')
                this.mostrarFiltroGruposTrabajo = true;

            this.filtro_grupos_trabajo.setValue('');

            if(
                ofe.ofe_recepcion_fnc_activo == 'SI' &&
                (
                    this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') ||
                    this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionAsociarDocumentoGrupoTrabajo')
                )
            ) {
                let existe = false;
                this.accionesBloque.forEach((valor: any, indice: number) => {
                    if(valor.id && valor.id === 'asignar_grupo_trabajo')
                        existe = true;
                });

                if(!existe) 
                    this.accionesBloque.push(
                        {id: 'asignar_grupo_trabajo', nombre: 'Asignar ' + this._grupo_trabajo}
                    );
            }

            if(
                ofe.ofe_recepcion_fnc_activo == 'SI' &&
                (
                    this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') ||
                    this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionEnviarValidacion')
                )
            ) {
                let indiceReclamo = this.accionesBloque.length;
                this.accionesBloque.forEach((valor: any, indice: number) => {
                    if(valor.id && valor.id === 'rechazo_documento')
                        indiceReclamo = indice + 1;
                });

                let existe = false;
                this.accionesBloque.forEach((valor: any, indice: number) => {
                    if(valor.id && valor.id === 'enviar_a_validacion')
                        existe = true;
                });

                if(!existe) {
                    this.accionesBloque.splice(
                        indiceReclamo, 0,
                        {id: 'enviar_a_validacion', nombre: 'Enviar a Validación '}
                    );
                }
            }

            if(
                ofe.ofe_recepcion_fnc_activo == 'SI' &&
                (
                    this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') ||
                    this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionDocumentoValidado')
                )
            ) {
                let existe = false;
                this.accionesBloque.forEach((valor: any, indice: number) => {
                    if(valor.id && valor.id === 'documento_validado')
                        existe = true;
                });

                if(!existe)
                    this.accionesBloque.push(
                        {id: 'documento_validado', nombre: 'Validar'}
                    );
            }

            if(
                ofe.ofe_recepcion_fnc_activo == 'SI' &&
                (
                    this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') ||
                    this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionDatosValidacion')
                )
            ) {
                let indiceReclamo = this.accionesBloque.length;
                this.accionesBloque.forEach((valor: any, indice: number) => {
                    if(valor.id && valor.id === 'rechazo_documento')
                        indiceReclamo = indice + 1;
                });

                let existe = false;
                this.accionesBloque.forEach((valor: any, indice: number) => {
                    if(valor.id && valor.id === 'datos_validacion')
                        existe = true;
                });

                if(!existe) {
                    this.accionesBloque.splice(
                        indiceReclamo, 0,
                        {id: 'datos_validacion', nombre: 'Datos Validación'}
                    );
                }
            }

            this.obtenerGruposTrabajoUsuario('gestor');
        } else {
            this.mostrarFiltroGruposTrabajo = false;
            this.filtro_grupos_trabajo.setValue('');
            this.filtro_grupos_trabajo_usuario.setValue('');

            this.accionesBloque.forEach((valor: any, indice: number) => {
                if(valor.id && valor.id === 'asignar_grupo_trabajo')
                    this.accionesBloque.splice(indice, 1);
            });

            this.accionesBloque.forEach((valor: any, indice: number) => {
                if(valor.id && valor.id === 'datos_validacion')
                    this.accionesBloque.splice(indice, 1);
            });

            this.accionesBloque.forEach((valor: any, indice: number) => {
                if(valor.id && valor.id === 'enviar_a_validacion')
                    this.accionesBloque.splice(indice, 1);
            });

            this.accionesBloque.forEach((valor: any, indice: number) => {
                if(valor.id && valor.id === 'documento_validado')
                    this.accionesBloque.splice(indice, 1);
            });

            this.columns.forEach((columna: any, indice: number) => {
                if(columna.prop && columna.prop === 'grupo_trabajo')
                    this.columns.splice(indice, 1);
            });

            this.columns.forEach((columna: any, indice: number) => {
                if(columna.prop && columna.prop === 'usuario_responsable_recibidos')
                    this.columns.splice(indice, 1);
            });
        }

        this.ofeRecepcionFncActivo = 'NO';
        this.arrCamposValidacion   = [];
        this.campo_validacion.setValue('');
        this.valor_campo_validacion.setValue('');
        this.mostrarComboValorCampoValidacion = false;

        if(ofe.ofe_recepcion_fnc_activo == 'SI' && ofe.ofe_recepcion_fnc_configuracion) {
            this.ofeRecepcionFncActivo        = ofe.ofe_recepcion_fnc_activo;
            this.ofeRecepcionFncConfiguracion = ofe.ofe_recepcion_fnc_configuracion;

            if(ofe.ofe_recepcion_fnc_configuracion.evento_recibo_bien) {
                ofe.ofe_recepcion_fnc_configuracion.evento_recibo_bien.forEach(configCampo => {
                    this.arrCamposValidacion.push(
                        {
                            'campo'      : this._baseService.sanitizarString(configCampo.campo),
                            'nombreCampo': configCampo.campo,
                            'tipo'       : configCampo.tipo ? configCampo.tipo : '',
                            'tabla'      : configCampo.tabla ? configCampo.tabla : ''
                        }
                    )
                });
            }
        }

        if (ofe.ofe_recepcion_eventos_contratados_titulo_valor && ofe.ofe_recepcion_eventos_contratados_titulo_valor.length > 0) {
            let existeAceptacionT = false;
            ofe.ofe_recepcion_eventos_contratados_titulo_valor.forEach(element => {
                if (element.evento == "ACEPTACIONT")
                    existeAceptacionT = true;
            });

            if (existeAceptacionT) {
                if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionConsultaAceptacionTacitaDocumentos')) {
                    let indiceConsultarEstadoDian = this.accionesBloque.length;
                    this.accionesBloque.forEach((valor: any, indice: number) => {
                        if(valor.id && valor.id === 'consultar_estado_dian')
                            indiceConsultarEstadoDian = indice + 1;
                    });

                    let existe = false;
                    this.accionesBloque.forEach((valor: any, indice: number) => {
                        if(valor.id && valor.id === 'consulta_aceptacion_tacita')
                            existe = true;
                    });

                    if(!existe) {
                        this.accionesBloque.splice(
                            indiceConsultarEstadoDian, 0,
                            {id: 'consulta_aceptacion_tacita', nombre: 'Consulta Aceptación Tácita'}
                        );
                    }
                }
            } else {
                this.accionesBloque.forEach((valor: any, indice: number) => {
                    if(valor.id && valor.id === 'consulta_aceptacion_tacita')
                        this.accionesBloque.splice(indice, 1);
                });
            }
        }

        let actor = this.arrActoresRadian.find(act => act.act_identificacion == ofe.ofe_identificacion && act.act_roles.includes('2'));

        if (actor !== undefined && (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionEnviarDocumentoRadian'))) {
            this.accionesBloque.unshift(
                { id: 'enviar_documento_radian', nombre: 'Enviar a RADIAN' }
            );
        } else {
            this.accionesBloque = this.accionesBloque.filter((valor: any) => valor.id !== 'enviar_documento_radian');
        }

        this.documentosTrackingRecepcion.actualizarAccionesLote(this.accionesBloque);
    }

    /**
     * Ejecuta la petición para agendar el proceso en background.
     *
     * @memberof DocumentosRecibidosComponent
     */
    agendarReporteBackground() {
        this.loading(true);
        let params = {
            tipo : 'recepcion-recibidos',
            json : JSON.stringify(this.getSearchParametersObject(true))
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
     * Se encarga de mostrar u ocultar el select de Resultado Estados Eventos Dian.
     *
     * @memberof DocumentosRecibidosComponent
     */
    mostrarSelectEventosDian(): void{
        this.mostrarSelectResEstadoDian = (this.estado_eventos_dian.value.length >= 1) ? false : true;
        if (this.mostrarSelectResEstadoDian)
            this.resEventosDian.setValue('');
    }

    /**
     * Realiza la consulta de los datos paramétricos de validación y asigna los valores encontrados al control correspondiente en los filtros del formulario.
     *
     * @private
     * @param {string} string Nombre del control del formulario que recibirá los datos
     * @param {string} clasificacion Clasificación de los datos que debe ser filtrada en la consulta
     * @memberof ModalEventosDocumentosComponent
     */
    private obtenerListaDatosParametricosValidacion(campo: string, clasificacion: string) {
        this.loading(true);
        this._datosParametricosService.listarDatosParametricosValidacion(campo, clasificacion).subscribe({
            next: ( res => {
                this.loading(false);
                this.arrValoresCamposValidacion = res.data.datos_parametricos_clasificacion;
            }),
            error: ( error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar los Datos Parametricos de Validación');
            })
        });
    }

    /**
     * Realiza modificaciones a valores y visibilidad de campos relacionados con los filtros de validación.
     *
     * @param {*} valorSeleccionado
     * @memberof DocumentosRecibidosComponent
     */
    public cambioFiltroCamposValidacion(valorSeleccionado) {
        this.mostrarComboValorCampoValidacion = false;
        this.valor_campo_validacion.setValue('');

        if(valorSeleccionado && valorSeleccionado.tipo && valorSeleccionado.tipo === 'parametrico' && valorSeleccionado.tabla && valorSeleccionado.tabla === this.tablaDatosParametricosValidacion) {
            this.mostrarComboValorCampoValidacion = true;

            this.obtenerListaDatosParametricosValidacion('Valor a Buscar', this._baseService.sanitizarString(valorSeleccionado.campo));
        }
    }

    /**
     * Obtiene la lista de grupos de trabajo con los cuales esta asociado el usuario autenticado.
     *
     * @param {string} tipoAsociacion Tipo de asociación del usuario con los grupos (gestor-validador)
     * @memberof DocumentosRecibidosComponent
     */
    public obtenerGruposTrabajoUsuario(tipoAsociacion: string) {
        this.loading(true);
        this.filtro_grupos_trabajo_usuario.setValue('');

        this._configuracionService.obtenerGruposTrabajoUsuario(tipoAsociacion).subscribe({
            next: ( res => {
                this.loading(false);
                this.arrGruposTrabajoUsuario = res.data;
            }),
            error: ( error => {
                this.loading(false);
                this.mostrarErrores(error, error.message);
            })
        });
    }
}