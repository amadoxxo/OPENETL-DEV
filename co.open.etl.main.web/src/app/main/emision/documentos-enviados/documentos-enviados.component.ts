import {Component, OnInit, ViewChild} from '@angular/core';
import {BaseComponentList} from 'app/main/core/base_component_list';
import {AbstractControl, FormGroup, FormBuilder} from '@angular/forms';
import {Auth} from '../../../services/auth/auth.service';
import {Router} from '@angular/router';
import * as moment from 'moment';
import swal from 'sweetalert2';
import {DocumentosTrackingComponent} from './../../commons/documentos-tracking/documentos-tracking.component';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {ModalDocumentosListaComponent} from '../../modals/modal-documentos-lista/modal-documentos-lista.component';
import {ModalDocumentosAnexosComponent} from '../../modals/modal-documentos-anexos/modal-documentos-anexos.component';
import {ModalReenvioEmailComponent} from './../../commons/modal-reenvio-email/modal-reenvio-email.component';
import {ConfiguracionService} from '../../../services/configuracion/configuracion.service';
import {DocumentosEnviadosService} from '../../../services/emision/documentos_enviados.service';
import {CommonsService} from '../../../services/commons/commons.service';
import {OpenEcmService} from '../../../services/ecm/openecm.service';
import {RadianService} from '../../../services/radian/radian.service';
import {ReportesBackgroundService} from '../../../services/reportes/reportes_background.service';
import {
    DocumentosTrackingColumnInterface,
    DocumentosTrackingInterface
} from '../../commons/documentos-tracking/documentos-tracking-interface';


class Parameters {
    cdo_fecha_envio_desde  : string;
    cdo_fecha_envio_hasta  : string;
    cdo_fecha_desde        : string;
    cdo_fecha_hasta        : string;
    ofe_id                 : number;
    adq_id?                : number;
    cdo_clasificacion?     : string;
    forma_pago?            : string;
    proceso?               : string;
    estado?                : string;
    estado_acuse_recibo?   : string;
    estado_recibo_bien?    : string;
    estado_eventos_dian?   : string;
    resultado_evento_dian? : string;
    estado_dian?           : string;
    cdo_origen?            : string;
    cdo_consecutivo?       : string;
    cdo_lote?              : string;
    rfa_prefijo?           : string;
    cdo_acuse_recibo?      : string;
    cdo_resultado_ws_crt?  : string;
    // cdo_gestion?        : string;
    start?                 : number;
    length?                : number;
    buscar?                : string;
    excel?                 : boolean;
    columnaOrden?          : string;
    ordenDireccion?        : string;
    ofe_filtro?            : string;
    ofe_filtro_buscar?     : string;
}

@Component({
    selector: 'app-documentos-enviados',
    templateUrl: './documentos-enviados.component.html',
    styleUrls: ['./documentos-enviados.component.scss']
})
export class DocumentosEnviadosComponent extends BaseComponentList implements OnInit, DocumentosTrackingInterface {

    @ViewChild('documentosTracking', {static: true}) documentosTracking: DocumentosTrackingComponent;

    public parameters: Parameters;
    public form: FormGroup;
    public aclsUsuario: any;
    public arrOrigen: Array<Object> = [
        {id: 'MANUAL', name: 'MANUAL'},
        {id: 'INTEGRACION', name: 'INTEGRACIÓN'}
    ];
    public arrDescargas: Array<Object> = [
        {id: 'pdf', name: 'PDF'},
        {id: 'xml-ubl', name: 'XML-UBL'},
        {id: 'ar-dian', name: 'AR DIAN'},
        {id: 'attacheddocument', name: 'ATTACHEDDOCUMENT'},
        {id: 'pdf-generar', name: 'PDF (GENERAR)'}
    ];
    public arrEnvioCorreo: Array<Object> = [
        {id: 'xml', name: 'XML'},
        {id: 'pdf', name: 'PDF'}
    ];
    public arrTipoDoc  : Array<Object> = [];
    public arrFormaPago: Array<any>    = [];
    public arrActoresRadian: Array<any>= [];
    public arrEstadoDoc: Array<Object> = [
        {id: 'ACTIVO', name: 'ACTIVO'},
        {id: 'INACTIVO', name: 'INACTIVO'}
    ];
    public arrEstadoDian: Array<Object> = [
        {id: 'aprobado', name: 'APROBADO'},
        {id: 'aprobado_con_notificacion', name: 'APROBADO CON NOTIFICACION'},
        {id: 'rechazado', name: 'RECHAZADO'},
        {id: 'en_proceso', name: 'EN PROCESO'}
    ];
    public arrInteroperabilidad: Array<Object> = [
        {id: 'SI', name: 'SÍ'},
        {id: 'NO', name: 'NO'}
    ];
    public arrGestionado: Array<Object> = [
        {id: 'SI', name: 'SÍ'},
        {id: 'NO', name: 'NO'}
    ];
    public arrAcuseRecibo: Array<Object> = [
        {id: 'SI', name: 'SÍ'},
        {id: 'NO', name: 'NO'}
    ];
    public arrReciboBien: Array<Object> = [
        {id: 'SI', name: 'SÍ'},
        {id: 'NO', name: 'NO'}
    ];
    public arrResultadoEventoDian: Array<Object> = [
        {id: 'exitoso', name: 'EXITOSO'},
        {id: 'fallido', name: 'FALLIDO'}
    ];

    public filtrosOfe           : Array<Object> = [];
    public accionesBloque       : Array<Object> = [];
    public ofe_id               : AbstractControl;
    public adq_id               : AbstractControl;
    public cdo_fecha_desde      : AbstractControl;
    public cdo_fecha_hasta      : AbstractControl;
    public cdo_fecha_envio_desde: AbstractControl;
    public cdo_fecha_envio_hasta: AbstractControl;
    public cdo_origen           : AbstractControl;
    public cdo_clasificacion    : AbstractControl;
    public forma_pago           : AbstractControl;
    public estado               : AbstractControl;
    public estado_acuse_recibo  : AbstractControl;
    public estado_recibo_bien   : AbstractControl;
    public estado_eventos_dian  : AbstractControl;
    public resultado_evento_dian: AbstractControl;
    public estado_dian          : AbstractControl;
    public cdo_lote             : AbstractControl;
    public cdo_consecutivo      : AbstractControl;
    public rfa_prefijo          : AbstractControl;
    public cdo_acuse_recibo     : AbstractControl;
    public cdo_resultado_ws_crt : AbstractControl;
    public ofe_filtro           : AbstractControl;
    public ofe_filtro_buscar    : AbstractControl;
    // public cdo_gestion: AbstractControl;

    public existeConsulta   : boolean = false;
    public mostrarOfeFiltros: boolean = false;
    public visorEcm         : boolean = false;
    public estadoVisorEcm   : boolean = false;
    public tituloHeader     : string = '';
    public mostrarComboEventosDian: boolean = false;
    public mostrarSelectResEstadoDian: boolean = true;

    //Modals
    private modalDocumentosLista           : any;
    private modalDocumentosAnexos          : any;
    private modalDocumentoInteroperabilidad: any;
    private modalEmails                    : any;

    public ofes: Array<any> = [];

    public trackingInterface: DocumentosTrackingInterface;

    public registros: any [] = [];

    public columns: DocumentosTrackingColumnInterface[] = [
        {name: 'Lote', prop: 'cdo_lote', sorteable: true, width: '110'},
        {name: 'Tipo', prop: 'cdo_clasificacion', sorteable: true, width: '60'},
        {name: 'Documento', prop: 'documento', sorteable: true, width: '150'},
        {name: 'Receptor', prop: 'adquirente', sorteable: true, width: '250'},
        {name: 'Fecha', prop: 'fecha', sorteable: true, width: '170'},
        {name: 'Moneda', prop: 'moneda', sorteable: true, width: '80'},
        {name: 'Valor', prop: 'valor_a_pagar', sorteable: true, width: '150', derecha: true},
        {name: 'Estado', prop: 'estado', sorteable: true, width: '100'},
        {name: 'Origen', prop: 'cdo_origen', sorteable: true, width: '120'}
    ];

    public arrEventosDian: Array<Object> = [
        { id: 'sin_estado',           name: 'SIN ESTADO'},
        { id: 'aceptacion_tacita',    name: 'ACEPTACIÓN TÁCITA'},
        { id: 'aceptacion_documento', name: 'ACEPTACIÓN EXPRESA'},
        { id: 'rechazo_documento',    name: 'RECLAMO (RECHAZO)'}
    ];

    /**
     * Crea una instancia de DocumentosEnviadosComponent.
     * 
     * @param {Auth} _auth
     * @param {OpenEcmService} _openEcm
     * @param {FormBuilder} fb
     * @param {Router} _router
     * @param {MatDialog} modal
     * @param {CommonsService} _commonsService
     * @param {ConfiguracionService} _configuracionService
     * @param {DocumentosEnviadosService} _documentosEnviadosService
     * @param {ReportesBackgroundService} _reportesBackgroundService
     * @memberof DocumentosEnviadosComponent
     */
    constructor(
        public _auth: Auth,
        public _openEcm: OpenEcmService,
        private fb: FormBuilder,
        private _router: Router,
        private modal: MatDialog,
        private _commonsService: CommonsService,
        private _configuracionService: ConfiguracionService,
        private _documentosEnviadosService: DocumentosEnviadosService,
        private _reportesBackgroundService: ReportesBackgroundService,
        private _radianService: RadianService
    ) {
        super();
        this.rows = [];
        this.parameters = new Parameters();
        this.trackingInterface = this;
        this.aclsUsuario = this._auth.getAcls();
        this.init();
    }

    private init() {
        this.initDataSort('cdo_fecha');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.form = this.fb.group({
            ofe_id               : this.requerido(),
            cdo_fecha_envio_desde: this.requerido(),
            cdo_fecha_envio_hasta: this.requerido(),
            cdo_fecha_desde      : [''],
            cdo_fecha_hasta      : [''],
            adq_id               : [''],
            cdo_origen           : [''],
            cdo_consecutivo      : [''],
            cdo_clasificacion    : [''],
            forma_pago           : [''],
            estado               : [''],
            estado_acuse_recibo  : [''],
            estado_recibo_bien   : [''],
            estado_eventos_dian  : [''],
            resultado_evento_dian: [''],
            estado_dian          : [''],
            cdo_lote             : [''],
            rfa_prefijo          : [''],
            cdo_acuse_recibo     : [''],
            cdo_resultado_ws_crt : [''],
            ofe_filtro           : [''],
            ofe_filtro_buscar    : [''],
            // cdo_gestion: ['']
        });
        
        this.ofe_id                = this.form.controls['ofe_id'];
        this.cdo_fecha_envio_desde = this.form.controls['cdo_fecha_envio_desde'];
        this.cdo_fecha_envio_hasta = this.form.controls['cdo_fecha_envio_hasta'];
        this.cdo_fecha_desde       = this.form.controls['cdo_fecha_desde'];
        this.cdo_fecha_hasta       = this.form.controls['cdo_fecha_hasta'];
        this.adq_id                = this.form.controls['adq_id'];
        this.cdo_origen            = this.form.controls['cdo_origen'];
        this.cdo_clasificacion     = this.form.controls['cdo_clasificacion'];
        this.forma_pago            = this.form.controls['forma_pago'];
        this.estado                = this.form.controls['estado'];
        this.estado_acuse_recibo   = this.form.controls['estado_acuse_recibo'];
        this.estado_recibo_bien    = this.form.controls['estado_recibo_bien'];
        this.estado_eventos_dian   = this.form.controls['estado_eventos_dian'];
        this.resultado_evento_dian = this.form.controls['resultado_evento_dian'];
        this.estado_dian           = this.form.controls['estado_dian'];
        this.cdo_lote              = this.form.controls['cdo_lote'];
        this.rfa_prefijo           = this.form.controls['rfa_prefijo'];
        this.cdo_consecutivo       = this.form.controls['cdo_consecutivo'];
        this.cdo_acuse_recibo      = this.form.controls['cdo_acuse_recibo'];
        this.cdo_resultado_ws_crt  = this.form.controls['cdo_resultado_ws_crt'];
        this.ofe_filtro            = this.form.controls['ofe_filtro'];
        this.ofe_filtro_buscar     = this.form.controls['ofe_filtro_buscar'];
        // this.cdo_interoperabilidad = this.form.controls['cdo_interoperabilidad'];
        // this.cdo_gestion = this.form.controls['cdo_gestion'];
        
        if (this._router.url == '/emision/documentos-enviados') {
            this.arrTipoDoc   = [{id: 'FC', name: 'FC'}, {id: 'NC', name: 'NC'}, {id: 'ND', name: 'ND'}];
            this.tituloHeader = 'Emisión';
        } else if (this._router.url == '/documento-soporte/documentos-enviados') {
            this.arrTipoDoc   = [{id: 'DS', name: 'DS'}, {id: 'DS_NC', name: 'DS_NC'}];
            this.tituloHeader = 'Documento Soporte';
            this.arrDescargas.splice(0, 1);
            this.arrDescargas.splice(2, 1);
        }

        this.form.controls['estado'].setValue('ACTIVO');
    }

    /**
     * Crea un JSON con los parámetros de búsqueda.
     *
     */
    public getSearchParametersObject(excel: boolean = false) {

        this.parameters.start = this.start;
        this.parameters.length = this.length;
        this.parameters.columnaOrden = this.columnaOrden;
        this.parameters.ordenDireccion = this.ordenDireccion;
        this.parameters.buscar = String(this.buscar);
        const fecha_envio_desde = this.cdo_fecha_envio_desde && this.cdo_fecha_envio_desde.value !== '' ? String(moment(this.cdo_fecha_envio_desde.value).format('YYYY-MM-DD')) : '';
        const fecha_envio_hasta = this.cdo_fecha_envio_hasta && this.cdo_fecha_envio_hasta.value !== '' ? String(moment(this.cdo_fecha_envio_hasta.value).format('YYYY-MM-DD')) : '';
        const fecha_desde = this.cdo_fecha_desde && this.cdo_fecha_desde.value !== '' && this.cdo_fecha_desde.value != undefined ? String(moment(this.cdo_fecha_desde.value).format('YYYY-MM-DD')) : '';
        const fecha_hasta = this.cdo_fecha_hasta && this.cdo_fecha_hasta.value !== '' && this.cdo_fecha_desde.value != undefined ? String(moment(this.cdo_fecha_hasta.value).format('YYYY-MM-DD')) : '';

        this.parameters.cdo_fecha_envio_desde = fecha_envio_desde;
        this.parameters.cdo_fecha_envio_hasta = fecha_envio_hasta;

        if (fecha_desde)
            this.parameters.cdo_fecha_desde = fecha_desde;
        else
            delete this.parameters.cdo_fecha_desde;
        if (fecha_hasta)
            this.parameters.cdo_fecha_hasta = fecha_hasta;
        else
            delete this.parameters.cdo_fecha_hasta;
        if (this.adq_id && this.adq_id.value)
            this.parameters.adq_id = this.adq_id.value;
        else
            delete this.parameters.adq_id;
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
        if(this.estado && this.estado.value)
            this.parameters.estado = this.estado.value;
        else 
            delete this.parameters.estado; 
        if(this.estado_acuse_recibo && this.estado_acuse_recibo.value)
            this.parameters.estado_acuse_recibo = this.estado_acuse_recibo.value;
        else 
            delete this.parameters.estado_acuse_recibo; 
        if(this.estado_recibo_bien && this.estado_recibo_bien.value)
            this.parameters.estado_recibo_bien = this.estado_recibo_bien.value;
        else 
            delete this.parameters.estado_recibo_bien; 
        if(this.estado_eventos_dian && this.estado_eventos_dian.value)
            this.parameters.estado_eventos_dian = this.estado_eventos_dian.value;
        else 
            delete this.parameters.estado_eventos_dian; 
        if(this.resultado_evento_dian && this.resultado_evento_dian.value)
            this.parameters.resultado_evento_dian = this.resultado_evento_dian.value;
        else 
            delete this.parameters.resultado_evento_dian; 
        if(this.estado_dian && this.estado_dian.value)
            this.parameters.estado_dian = this.estado_dian.value;
        else 
            delete this.parameters.estado_dian; 
        if (this.cdo_lote && this.cdo_lote.value)
            this.parameters.cdo_lote = this.cdo_lote.value;
        else
            delete this.parameters.cdo_lote;
        if (this.rfa_prefijo && this.rfa_prefijo.value.trim())
            this.parameters.rfa_prefijo = this.rfa_prefijo.value;
        else
            delete this.parameters.rfa_prefijo;
        if (this.cdo_acuse_recibo && this.cdo_acuse_recibo.value)
            this.parameters.cdo_acuse_recibo = this.cdo_acuse_recibo.value;
        else
            delete this.parameters.cdo_acuse_recibo;
        if (this.cdo_resultado_ws_crt && this.cdo_resultado_ws_crt.value)
            this.parameters.cdo_resultado_ws_crt = this.cdo_resultado_ws_crt.value;
        else
            delete this.parameters.cdo_resultado_ws_crt;
        // if(this.cdo_interoperabilidad && this.cdo_interoperabilidad.value)
        //     this.parameters.cdo_interoperabilidad = this.cdo_interoperabilidad.value; 
        // if(this.cdo_gestion && this.cdo_gestion.value)
        //     this.parameters.cdo_gestion = this.cdo_gestion.value; 
        if (excel)
            this.parameters.excel = true;
        else
            delete this.parameters.excel;
        if (this.ofe_filtro && this.ofe_filtro.value && this.ofe_filtro_buscar && this.ofe_filtro_buscar.value) {
            this.parameters.ofe_filtro = this.ofe_filtro.value;
            this.parameters.ofe_filtro_buscar = this.ofe_filtro_buscar.value;
        } else {
            delete this.parameters.ofe_filtro;
            delete this.parameters.ofe_filtro_buscar;
        }

        if (this._router.url == '/emision/documentos-enviados') {
            this.parameters.proceso = 'emision';
        } else if (this._router.url == '/documento-soporte/documentos-enviados') {
            this.parameters.proceso = 'documento_soporte';
        }

        return this.parameters;
    }

    /**
     * Se encarga de traer la data de los documentos sin envío.
     *
     */
    public loadDocumentosEnviados(): void {
        this.loading(true);
        const parameters = this.getSearchParametersObject();
        this._documentosEnviadosService.listar(parameters).subscribe(
            res => {
                this.registros.length = 0;
                this.visorEcm = (this.estadoVisorEcm) ? true : false;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let adquirente = reg.get_configuracion_adquirente && reg.get_configuracion_adquirente.nombre_completo ? reg.get_configuracion_adquirente.nombre_completo : (reg.get_configuracion_adquirente && reg.get_configuracion_adquirente.adq_razon_social ? reg.get_configuracion_adquirente.adq_razon_social : '');
                    if (adquirente === '' && reg.get_configuracion_adquirente)
                        adquirente = reg.get_configuracion_adquirente.adq_primer_nombre + ' ' + reg.get_configuracion_adquirente.adq_primer_apellido;
                    let moneda_extranjera = reg.get_parametros_moneda_extranjera && reg.get_parametros_moneda_extranjera.mon_codigo !== 'COP' ? reg.get_parametros_moneda_extranjera.mon_codigo : null;
                    let moneda = reg.get_parametros_moneda ? reg.get_parametros_moneda.mon_codigo : 'COP';
                    if (moneda_extranjera)
                        moneda = moneda_extranjera;

                    this.registros.push(
                        {
                            'cdo_id'                                       : reg.cdo_id,
                            'cdo_consecutivo'                              : reg.cdo_consecutivo,
                            'rfa_prefijo'                                  : reg.rfa_prefijo ? reg.rfa_prefijo : '',
                            'cdo_lote'                                     : reg.cdo_lote,
                            'ofe_id'                                       : reg.ofe_id,
                            'adq_id'                                       : reg.adq_id,
                            'cdo_clasificacion'                            : reg.cdo_clasificacion,
                            'forma_pago'                                   : reg.forma_pago,
                            'cdo_cufe'                                     : reg.cdo_cufe,
                            'documento'                                    : (reg.rfa_prefijo ? reg.rfa_prefijo : '') + ' ' + reg.cdo_consecutivo,
                            'get_configuracion_adquirente.adq_razon_social': adquirente,
                            'adquirente'                                   : adquirente,
                            'adq_identificacion'                           : reg.get_configuracion_adquirente.adq_identificacion,
                            'fecha'                                        : reg.cdo_fecha + ' ' + reg.cdo_hora,
                            'moneda'                                       : moneda,
                            'valor_a_pagar'                                : moneda_extranjera ? reg.cdo_valor_a_pagar_moneda_extranjera : reg.cdo_valor_a_pagar,
                            'cdo_origen'                                   : reg.cdo_origen,
                            'cdo_procesar_documento'                       : reg.cdo_procesar_documento,
                            'estado'                                       : reg.estado,
                            'estado_eventos_dian'                          : reg.estado_eventos_dian,
                            'cdo_representacion_grafica_documento'         : reg.cdo_representacion_grafica_documento,
                            'ofe_identificacion'                           : reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion,
                            'ofe_eventos_notificacion'                     : reg.get_configuracion_obligado_facturar_electronicamente.ofe_eventos_notificacion !== undefined && reg.get_configuracion_obligado_facturar_electronicamente.ofe_eventos_notificacion !== null && reg.get_configuracion_obligado_facturar_electronicamente.ofe_eventos_notificacion !== '' ? JSON.parse(reg.get_configuracion_obligado_facturar_electronicamente.ofe_eventos_notificacion) : '',
                            'ofe_cadisoft_activo'                          : reg.get_configuracion_obligado_facturar_electronicamente.ofe_cadisoft_activo,
                            'cdo_fecha_validacion_dian'                    : reg.cdo_fecha_validacion_dian,
                            'estado_documento'                             : reg.estado_documento,
                            'estado_notificacion'                          : reg.estado_notificacion,
                            'estado_dian'                                  : reg.estado_dian,
                            'estado_do'                                    : reg.estado_do,
                            'estado_ubl'                                   : reg.estado_ubl,
                            'estado_attacheddocument'                      : reg.estado_attacheddocument,
                            'aplica_documento_anexo'                       : reg.aplica_documento_anexo,
                            'notificacion_tamano_superior'                 : reg.notificacion_tamano_superior,
                            'notificacion_tipo_evento'                     : reg.notificacion_tipo_evento
                        }   
                    );
                });
                this.totalElements = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow = this.length !== -1 ? this.length : this.totalElements;
                if (this.rows.length > 0) {
                    this.existeConsulta = true;
                } else {
                    this.existeConsulta = false;
                }
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Documentos Enviados', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    ngOnInit() {
        this.adq_id.disable();

        if (this._router.url == '/emision/documentos-enviados') {
            if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'EmisionDocumentosEnviadosDescargarCertificado')) {
                this.arrDescargas.push(
                    { id: 'certificado', name: 'CERTIFICADO' }
                );
            }
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'EmisionDocumentosEnviadosDescargarJson')) {
            this.arrDescargas.push(
                { id: 'json', name: 'JSON' }
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'EmisionTransmitirEdm')) {
            this.accionesBloque.push(
                { id: 'transmitir_edm', nombre: 'Transmitir a EDM' }
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'CambiarEstadoDocumentosEnviados')) {
            this.accionesBloque.push(
                { id: 'cambio_estado_doc', nombre: 'Cambio Estado Documento' }
            );
        }

        if (this._router.url == '/emision/documentos-enviados') {
            if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'ModificarDocumentoPickupCash')) {
                this.accionesBloque.push(
                    { id: 'modificar_documento_pickup_cash', nombre: 'Modificar Documento Pickup Cash' }
                );
            }
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'ModificarDocumento')) {
            this.accionesBloque.push(
                { id: 'modificar_documento', nombre: 'Modificar Documento' }
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || (this._auth.existePermiso(this.aclsUsuario.permisos, 'EmisionAceptacionTacita'))) {
            this.accionesBloque.push(
                { id: 'aceptacion_tacita', nombre: 'Aceptación Tácita' }
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'EmisionConsultarDocumentoDian')) {
            this.accionesBloque.push(
                { id: 'consultar_estado_dian', nombre: 'Consultar Estado DIAN' }
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'EmisionEnviarDocumentoDian')) {
            this.accionesBloque.push(
                { id: 'enviar_documentos_dian', nombre: 'Enviar Documentos a la DIAN' }
            );
        }
        
        this.cargarOfes();
    }

    /**
     * Carga los OFEs en el select de emisores.
     *
     */
    private cargarOfes() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false').subscribe(
            result => {
                this.loading(false);
                this.ofes = [];
                result.data.ofes.forEach(ofe => {
                    if(ofe.ofe_emision === 'SI' && this.tituloHeader !== 'Documento Soporte') {
                        ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                        this.ofes.push(ofe);
                    } else if(ofe.ofe_documento_soporte === 'SI' && this.tituloHeader === 'Documento Soporte') {
                        ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                        this.ofes.push(ofe);
                    }
                });
                this.arrFormaPago = result.data.formas_pago;
                this.arrActoresRadian = result.data.actores_radian;
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los OFEs', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Recarga el datatable con la información filtrada.
     *
     */
    searchDocumentos(values): void {
        this.loading(true);
        if (this.form.valid) {
            this.onPage({
                offset: 0
            });
            this.documentosTracking.tracking.offset = 0;
            // this.getData();
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
            this._documentosEnviadosService.descargarDocumentos(tiposDescargas, ids, ofeId).subscribe(
                response => {
                    this.loading(false);
                },
                (error) => {
                    this.loading(false);
                }
            );
        }
    }

    /**
     * Responde a las diferentes acciones del combo de Acciones en Bloque.
     *
     */
    accionesEnBloque(selectedOption) {
        let registros = {lote: '', documentos: []};
        let docs = '';
        let ids = '';
        let documentos = '';
        let mensaje = '';
        let that = this;
        let ofeId = this.ofe_id.value ? this.ofe_id.value : null;
        if (this.selected.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Acciones en Bloque', 'Ok, entiendo', 'btn btn-warning');
        } else {
            this.selected.forEach(reg => {
                ids += reg.cdo_id + ',';
                documentos += reg.rfa_prefijo + ' ' + reg.cdo_consecutivo + ', ';
            });
            ids = ids.slice(0, -1);
            documentos = documentos.slice(0, -2);
            switch (selectedOption) {
                case 'cambio_estado_doc':
                    let docsCambioEstado = [];
                    this.selected.forEach(reg => {
                        ids  += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsCambioEstado.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Cambio Estado Documentos', 'Ok, entiendo', 'btn btn-warning');
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
                            that._documentosEnviadosService.cambiarEstadoDocumentosEnviados(registros.documentos.join(',')).subscribe(
                                response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    let mensajeFinal = '<ul>';
                                    response.message != '' ? mensajeFinal += '<li style="text-align:left;">' + response.message + '</li>' : mensajeFinal += '';
                                    let documentosErrores = '';
                                    response.errors.forEach((error) => {
                                        documentosErrores += '<li style="text-align:left;">' + error + '</li>';
                                    });
                                    mensajeFinal += documentosErrores + '</ul>';
                                    that.showSuccess(mensajeFinal, 'success', 'Cambio de estado', 'Ok', 'btn btn-success');
                                },
                                error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al cambiar el estado de los Documentos');
                                }
                            );
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;
                case 'consultar_estado_dian':
                    let docsConsultarestadoDian = [];
                    this.selected.forEach(reg => {
                        ids  += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsConsultarestadoDian.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Consultar Estado Dian', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Consultar el estado DIAN para los siguientes documentos seleccionados?:</span><br><br><ul>';
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
                            that._documentosEnviadosService.agendarConsultaEstadoDianDocumentosEnviados(registros.documentos.join(',')).subscribe(
                                response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Consultar Estado Dian', 'Ok', 'btn btn-success');
                                },
                                error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al intentar agendar la consulta de estados en la DIAN');
                                }
                            );
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;
                case 'aceptacion_tacita':
                    let docsConsultaAceptadoTacitamente = [];
                    this.selected.forEach(reg => {
                        ids  += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsConsultaAceptadoTacitamente.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Consultar Aceptación Tácita', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Consultar el estado de Aceptación Tácita para los siguientes documentos seleccionados? :</span><br><br><ul>';
                        docsConsultaAceptadoTacitamente.forEach((doc) => {
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
                            that._documentosEnviadosService.agendarEstadosAceptacionTacita(registros.documentos.join(',')).subscribe(
                                response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Agendar Estado Aceptado Tacitamente', 'Ok', 'btn btn-success');
                                },
                                error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al intentar agendar el estado Aceptado Tacitamente');
                                }
                            );
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;
                case 'transmitir_edm':
                    let docsTransmitirEdm = [];
                    this.selected.forEach(reg => {
                        ids  += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsTransmitirEdm.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Transmitir a EDM', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Desea transmitir a EDM los siguientes documentos?:</span><br><br><ul>';
                        docsTransmitirEdm.forEach((doc) => {
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
                            that._documentosEnviadosService.transmitirEdm({'cdoIds': registros.documentos.join(',')}).subscribe(
                                response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Transmitir EDM', 'Ok', 'btn btn-success');
                                },
                                error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al intentar transmitir a EDM');
                                }
                            );
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;
                case 'enviar_documento_radian':
                    let docsEnviarRadian = [];
                    let docsPrefijoConsecutivo = [];
                    this.selected.forEach(reg => {
                        docsPrefijoConsecutivo.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        docsEnviarRadian.push({'act_identificacion' : reg.ofe_identificacion, 'rol_id': 1, 'cufe': reg.cdo_cufe});
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
                            
                            that._radianService.agendarEstadoRadEdi(objData).subscribe(
                                response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Enviar a RADIAN', 'Ok', 'btn btn-success');
                                },
                                error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al intentar enviar a RADIAN');
                                });

                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;
                case 'enviar_documentos_dian':
                    let docsEnviarDian = [];
                    this.selected.forEach(reg => {
                        ids  += reg.cdo_id + '|';
                        docs += reg.cdo_consecutivo + ', ';
                        docsEnviarDian.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        registros.documentos.push(reg.cdo_id);
                    });

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Cambio Estado Documentos', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Está seguro de enviar a la DIAN a los siguientes documentos?:</span><br><br><ul>';
                        docsEnviarDian.forEach((doc) => {
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
                            that._documentosEnviadosService.enviarDocumentosDian(registros).subscribe(
                                response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Envío a la DIAN', 'Ok', 'btn btn-success');
                                },
                                error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al enviar a la DIAN los documentos');
                                }
                            );
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break;
                case 'modificar_documento':
                    // Esta opción aplica para documentos rechazados por la DIAN y que no sean de DHL Express y cuya RG sea 9
                    let docsModificar    = [];
                    let docsNoPermitidos = [];
                    this.selected.forEach(reg => {
                        let documentoRechazado = false;
                        console.log(reg.estado_ubl);
                        if((reg.estado_do && reg.estado_do === 'DO_FALLIDO') || (reg.estado_ubl && reg.estado_ubl === 'UBL_FALLIDO'))
                            documentoRechazado = true;
                        const dhlExpress = reg.ofe_identificacion !== '860502609' ? true : false;
                        const rg9 = reg.cdo_representacion_grafica_documento !== '9' ? true : false;

                        if(documentoRechazado && dhlExpress && rg9) {
                            ids += reg.cdo_id + '|';
                            docs += reg.cdo_consecutivo + ', ';
                            docsModificar.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                            registros.documentos.push(reg.cdo_id);
                        } else {
                            docsNoPermitidos.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        }
                    });

                    if(docsNoPermitidos.length > 0) {
                        mensaje = '<span>Los siguientes documentos no cumplen las condiciones para ser modificados, utilice la opción \'Modificar Documento Pickup Cash\':</span><br><br><ul>';
                        docsNoPermitidos.forEach((doc) => {
                            mensaje += "<li style='text-align:left;'>" + doc + "</li>";
                        });
                        mensaje += '</ul>';

                        this.showError(mensaje, 'warning', 'Modificar Documentos', 'Ok, entiendo', 'btn btn-warning');

                        return false;
                    }

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Modificar Documentos', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Está seguro de modificar los siguientes documentos?:</span><br><br><ul>';
                        docsModificar.forEach((doc) => {
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
                            that._documentosEnviadosService.modificarDocumentosDian(registros).subscribe(
                                response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Modificar Documentos', 'Ok', 'btn btn-success');
                                },
                                error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al modificar los documentos');
                                }
                            );
                        }
                        selectedOption = null;
                    }).catch(swal.noop);

                    break
                case 'modificar_documento_pickup_cash':
                    // Esta opción solamente aplica para documentos rechazados por la DIAN que sean de DHL Express y cuya RG sea 9
                    let docsModificarPickupCash    = [];
                    let docsNoPermitidosPickupCash = [];
                    this.selected.forEach(reg => {
                        let documentoRechazado = false;
                        if(reg.estado_do && reg.estado_do === 'DO_FALLIDO')
                            documentoRechazado = true;
                        const dhlExpress = reg.ofe_identificacion === '860502609' ? true : false;
                        const rg9 = reg.cdo_representacion_grafica_documento === '9' ? true : false;
                        
                        if(documentoRechazado && dhlExpress && rg9) {
                            ids += reg.cdo_id + '|';
                            docs += reg.cdo_consecutivo + ', ';
                            docsModificarPickupCash.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                            registros.documentos.push(reg.cdo_id);
                        } else {
                            docsNoPermitidosPickupCash.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                        }
                    });

                    if(docsNoPermitidosPickupCash.length > 0) {
                        mensaje = '<span>Los siguientes documentos no cumplen las condiciones para ser modificados, utilice la opción \'Modificar Documento\' si la edición la realiza desde el módulo \'Facturación Web\' o la opción \'Cambio Estado Documento\' si la edición la realiza desde el módulo \'Creación Documentos por Excel\':</span><br><br><ul>';
                        docsNoPermitidosPickupCash.forEach((doc) => {
                            mensaje += "<li style='text-align:left;'>" + doc + "</li>";
                        });
                        mensaje += '</ul>';

                        this.showError(mensaje, 'warning', 'Modificar Documentos Pickup Cash', 'Ok, entiendo', 'btn btn-warning');

                        return false;
                    }

                    if (registros.documentos.length == 0) {
                        this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Modificar Documentos Pickup Cash', 'Ok, entiendo', 'btn btn-warning');
                        return false;
                    } else {
                        mensaje = '<span>¿Está seguro de modificar los siguientes documentos?:</span><br><br><ul>';
                        docsModificarPickupCash.forEach((doc) => {
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
                            that._documentosEnviadosService.modificarDocumentosDianPickupCash(registros).subscribe(
                                response => {
                                    that.recargarLista();
                                    that.loading(false);
                                    that.showSuccess(response.message, 'success', 'Modificar Documentos Pickup Cash', 'Ok', 'btn btn-success');
                                },
                                error => {
                                    that.loading(false);
                                    that.mostrarErrores(error, 'Error al modificar los documentos pickup cash');
                                }
                            );
                        }
                        selectedOption = null;
                    }).catch(swal.noop);
                    break
                default:
                    break;
            }
        }
        this.selected = [];
        selectedOption = null;
    }

    /**
     * Permite abrir la modal para el reenvio de correos.
     *
     * @param string tiposDocumentos
     */
    sendDocs(tipos) {
        let ids = '';
        let docsEmails = [];
        let registros = {emails: []};

        if (this.selected.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Envío de Documentos', 'Ok, entiendo', 'btn btn-warning');
        } else {
            this.loading(true);

            this.selected.forEach(reg => {
                ids += reg.cdo_id + ',';
                docsEmails.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                registros.emails.push(reg.cdo_id);
            });

            let dataDocumentos = {
                'cdo_ids': registros.emails.join(','),
                'emails_procesar': docsEmails
            }

            const modalConfig = new MatDialogConfig();
            modalConfig.autoFocus = true;
            modalConfig.width = '500px';
            modalConfig.data = {
                documentos           : dataDocumentos,
                parent               : this,
            };
            this.modalEmails = this.modal.open(ModalReenvioEmailComponent, modalConfig);
        }
    }

    /**
     * Gestiona el evento de paginacion de la grid.
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
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'cdo_lote':
                this.columnaOrden = 'lote';
                break;
            case 'documento':
                this.columnaOrden = 'documento';
                break;
            case 'cdo_clasificacion':
                this.columnaOrden = 'clasificacion';
                break;
            case 'adquirente':
                this.columnaOrden = 'receptor';
                break;
            case 'moneda':
                this.columnaOrden = 'moneda';
                break;
            case 'valor_a_pagar':
                this.columnaOrden = 'valor';
                break;
            case 'cdo_origen':
                this.columnaOrden = 'origen';
                break;
            case 'fecha':
                this.columnaOrden = 'fecha';
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
     * Efectua la carga de datos.
     *
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadDocumentosEnviados();
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
     * Recarga el listado en base al término de búsqueda.
     *
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.recargarLista();
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
            case 'ver-documentos-anexos':
                this.openModalDocumentosAnexos(item);
                break;
            case 'interoperabilidad':
                this.openModalDocumentoInteroperabilidad(item);
                break;
            case 'ver-notificacion':
                // this.openModalDocumentoInteroperabilidad(item);
                break;
            case 'ver-historico-gestion':
                // this.openModalHistoricoGestion(item);
                break;
            default:
                break;
        }
    }

    /**
     * Gestiona la acción del botón de ver un registro
     *
     */
    onDescargarItems(selected: any[], tipos) {
        this.selected = selected;
        this.downloadDocs(tipos);
    }

    /**
     * Gestiona la acción del botón de envío por correo de documentos
     *
     */
    onEnviarItems(selected: any[], tipos) {
        this.selected = selected;
        this.sendDocs(tipos);
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
     * Metodo Interface para agendar los reportes en background de documentos enviados.
     *
     * @memberof DocumentosEnviadosComponent
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
            this._documentosEnviadosService.descargarExcel(this.getSearchParametersObject(true)).subscribe(
                response => {
                    this.loading(false);
                },
                (error) => {
                    this.loading(false);
                    this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar excel de Documentos Enviados', 'OK', 'btn btn-danger');
                }
            );
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
     * Apertura una ventana modal para ver los adjuntos de un documento.
     *
     * @param usuario
     */
    public openModalDocumentosAnexos(item: any): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '800px';
        modalConfig.data = {
            item: item,
            parent: this
        };
        this.modalDocumentosAnexos = this.modal.open(ModalDocumentosAnexosComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar los adjuntos de un documento.
     *
     */
    public closeModalDocumentosAnexos(): void {
        if (this.modalDocumentosAnexos) {
            this.modalDocumentosAnexos.close();
            this.modalDocumentosAnexos = null;
        }
    }

    /**
     * Apertura una ventana modal para ver documentos.
     *
     * @param usuario
     */
    public openModalDocumentoInteroperabilidad(item: any): void {
        let params = {
            cdo_id_daop: item.cdo_id,
            ofe_id_daop: item.ofe_id,
            adq_id_daop: item.adq_id
        };
        this.loading(true);
        this._documentosEnviadosService.getDocumentoEmitido(params)
            .subscribe(result => {
                const modalConfig = new MatDialogConfig();
                modalConfig.autoFocus = true;
                modalConfig.width = '800px';
                modalConfig.data = {
                    item: item,
                    parent: this,
                    resultado: result
                };
                this.loading(false);
            }, error => {
                this.loading(false);
            });

    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un documento.
     *
     */
    public closeModalDocumentoInteroperabilidad(): void {
        if (this.modalDocumentoInteroperabilidad) {
            this.modalDocumentoInteroperabilidad.close();
            this.modalDocumentoInteroperabilidad = null;
        }
    }

    /**
     * Monitoriza cuando el valor del select de OFEs cambia.
     *
     */
    ofeHasChanged(ofe) {
        this.mostrarComboEventosDian = false;
        if (ofe.ofe_emision_eventos_contratados_titulo_valor) {
            ofe.ofe_emision_eventos_contratados_titulo_valor.find(e => {
                if (e.evento === 'ACEPTACIONT') {
                    this.mostrarComboEventosDian = true;   
                }
            });
        }
        this.ofe_filtro.setValue('');
        this.ofe_filtro_buscar.setValue('');
        // Si un OFE tiene filtros configurados se deben cargar
        // y mostar los campos correspondientes en el formulario
        if (!this.isEmpty(ofe.ofe_filtros) && ofe.ofe_filtros && ofe.ofe_filtros !== 'null') {
            this.filtrosOfe = Object.keys(ofe.ofe_filtros)
                .map(key => ({id: key, name: ofe.ofe_filtros[key]}));
            this.mostrarOfeFiltros = true;
        } else {
            this.mostrarOfeFiltros = false;
        }
        this.estadoVisorEcm = this._openEcm.validarVisorEcm(ofe);

        let actor = this.arrActoresRadian.find(act => act.act_identificacion == ofe.ofe_identificacion && act.act_roles.includes('1'));

        if (actor !== undefined && (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'EmisionEnviarDocumentoRadian'))) {
            this.accionesBloque.unshift(
                { id: 'enviar_documento_radian', nombre: 'Enviar a RADIAN' }
            );
        } else {
            this.accionesBloque = this.accionesBloque.filter((valor: any) => valor.id !== 'enviar_documento_radian');
        }

        this.documentosTracking.actualizarAccionesLote(this.accionesBloque);
    }

    /**
     * De acuerdo al valor seleccionado en el combo ofe_fitros
     * limpia el valor del campo ofe_filtro_buscar
     *
     * @param {string} value
     * @memberof DocumentosEnviadosComponent
     */
    actualizaFiltroOfe(value) {
        if (!value || value == '')
            this.ofe_filtro_buscar.setValue('');
    }

    /**
     * Ejecuta la petición para agendar el proceso en background.
     *
     * @memberof DocumentosEnviadosComponent
     */
    agendarReporteBackground() {
        this.loading(true);
        let params = {
            tipo : 'emision-enviados',
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
     * Se encarga de habilitas o deshabilitar el select de Resultado Evento Dian.
     *
     * @memberof DocumentosEnviadosComponent
     */
    mostrarSelectEventosDian(): void {
        if (this.estado_eventos_dian.value.length !== 0) {
            this.mostrarSelectResEstadoDian = false;   
        } else {
            this.mostrarSelectResEstadoDian = true;
            this.resultado_evento_dian.setValue('');
        }
    }
}