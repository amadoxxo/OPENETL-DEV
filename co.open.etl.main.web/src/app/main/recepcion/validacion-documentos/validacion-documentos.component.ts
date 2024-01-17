import { Router } from '@angular/router';
import { concat, Observable, of, Subject } from 'rxjs';
import { Component, OnInit, ViewChild } from '@angular/core';
import { BaseComponentList } from 'app/main/core/base_component_list';
import { AbstractControl, FormGroup, FormBuilder } from '@angular/forms';
import { debounceTime, distinctUntilChanged, filter, finalize, switchMap, tap, catchError } from 'rxjs/operators';
import { DocumentosTrackingRecepcionComponent } from '../../commons/documentos-tracking-recepcion/documentos-tracking-recepcion.component';
import { ModalEventosDocumentosComponent } from '../../commons/modal-eventos-documentos/modal-eventos-documentos.component';
import { DocumentosTrackingRecepcionColumnInterface, DocumentosTrackingRecepcionInterface } from '../../commons/documentos-tracking-recepcion/documentos-tracking-recepcion-interface';

import { Auth } from '../../../services/auth/auth.service';
import { CommonsService } from '../../../services/commons/commons.service';
import { ConfiguracionService } from '../../../services/configuracion/configuracion.service';
import { DocumentosRecibidosService } from '../../../services/recepcion/documentos_recibidos.service';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';
import { NgSelectComponent } from '@ng-select/ng-select';
import { Usuario } from '../../models/usuario.model';
import * as moment from 'moment';
import swal from 'sweetalert2';

import { BaseService } from '../../../services/core/base.service';
import { DatosParametricosValidacionService } from './../../../services/proyectos-especiales/recepcion/fnc/validacion/datos-parametricos-validacion.service';
import { ReportesBackgroundService } from 'app/services/reportes/reportes_background.service';
import { JwtHelperService } from '@auth0/angular-jwt';

class Parameters {
    cdo_fecha_validacion_dian_desde?: string;
    cdo_fecha_validacion_dian_hasta?: string;
    cdo_fecha_desde                 : string;
    cdo_fecha_hasta                 : string;
    ofe_id                          : number;
    pro_id?                         : number;
    cdo_usuario_responsable?        : number;
    cdo_origen?                     : string;
    cdo_clasificacion?              : string;
    cdo_consecutivo?                : string;
    rfa_prefijo?                    : string;
    estado_validacion?              : string;
    estado?                         : string;
    length?                         : number;
    buscar?                         : string;
    columnaOrden?                   : string;
    ordenDireccion?                 : string;
    campo_validacion?               : string;
    valor_campo_validacion?         : string;
    filtro_grupos_trabajo_usuario?  : string;
    excel?                          : boolean;
    pag_anterior?                   : string;
    pag_siguiente?                  : string;
}

interface DataDocumentosValidacion {
    ofe_id                                : string ;
    cdo_ids                               : string ;
    documentos_procesar                   : Array<string> ;
    estado_validacion_validado            : object ;
    estado_validacion_en_proceso_pendiente: object ;
}

@Component({
    selector: 'app-validacion-documentos',
    templateUrl: './validacion-documentos.component.html',
    styleUrls: ['./validacion-documentos.component.scss']
})
export class ValidacionDocumentosComponent extends BaseComponentList implements OnInit, DocumentosTrackingRecepcionInterface {
    
    @ViewChild('documentosTrackingRecepcion', {static: true}) documentosTrackingRecepcion: DocumentosTrackingRecepcionComponent;
    @ViewChild('selectUsuarios', { static: true }) selectUsuarios: NgSelectComponent;


    public parameters : Parameters;
    public form       : FormGroup;
    public aclsUsuario: any;

    public arrOrigen  : Array<Object> = [
        {id: 'MANUAL',         name: 'MANUAL'},
        {id: 'RPA',            name: 'RPA'},
        {id: 'NO-ELECTRONICO', name: 'NO-ELECTRONICO'},
        {id: 'CORREO',         name: 'CORREO'},
    ];

    public arrTipoDoc: Array<Object> = [
        {id: 'FC', name: 'FC'},
        {id: 'NC', name: 'NC'},
        {id: 'ND', name: 'ND'}
    ];

    public arrEstadoRegistro: Array<Object> = [
        {id: 'ACTIVO', name: 'ACTIVO'},
        {id: 'INACTIVO', name: 'INACTIVO'}
    ];

    public arrEstadoValidacion: Array<Object> = [
        {id: 'pendiente', name: 'PENDIENTE'},
        {id: 'validado',  name: 'VALIDADO'},
        {id: 'rechazado', name: 'RECHAZADO'},
        {id: 'pagado',    name: 'PAGADO'}
    ];

    public arrDescargas: Array<Object> = [
        {id: 'pdf',     name: 'PDF'},
        {id: 'xml-ubl', name: 'XML-UBL'}
    ];

    public arrGruposTrabajoUsuario: Array<any> = [];
    public mostrarFiltroGruposTrabajo: boolean = false;

    public ofe_id                         : AbstractControl;
    public pro_id                         : AbstractControl;
    public cdo_fecha_desde                : AbstractControl;
    public cdo_fecha_hasta                : AbstractControl;
    public cdo_fecha_validacion_dian_desde: AbstractControl;
    public cdo_fecha_validacion_dian_hasta: AbstractControl;
    public cdo_origen                     : AbstractControl;
    public cdo_usuario_responsable        : AbstractControl;
    public usu_identificacion_nombre      : AbstractControl;
    public cdo_clasificacion              : AbstractControl;
    public estado_validacion              : AbstractControl;
    public cdo_consecutivo                : AbstractControl;
    public rfa_prefijo                    : AbstractControl;
    public campo_validacion               : AbstractControl;
    public valor_campo_validacion         : AbstractControl;
    public filtro_grupos_trabajo_usuario  : AbstractControl;
    public estado                         : AbstractControl;

    public trackingRecepcionInterface : DocumentosTrackingRecepcionInterface;

    public existeConsulta        : boolean = false;
    public ofes                  : Array<any> = [];
    public registros             : any [] = [];
    public accionesBloque        : Array<Object> = [];
    public arrReenvioNotificacion: Array<Object> = [];
    public usuarios$             : Observable<Usuario[]>;
    public usuariosInput$        = new Subject<string>();
    public usuariosLoading       = false;
    public selectedUsuId         : any;
    public paginador             : string;
    public linkSiguiente         : string;
    public linkAnterior          : string;

    public ofeRecepcionFncActivo : string = 'NO';
    public ofeRecepcionFncConfiguracion: any [] = [];

    public columns: DocumentosTrackingRecepcionColumnInterface[] = [
        {name: 'Tipo',         prop: 'cdo_clasificacion',       sorteable: true,  width: '60'},
        {name: 'Documento',    prop: 'documento',               sorteable: true,  width: '150'},
        {name: 'Emisor',       prop: 'proveedor',               sorteable: true,  width: '250'},
        {name: 'Fecha',        prop: 'fecha',                   sorteable: true,  width: '170'},
        {name: 'Moneda',       prop: 'moneda',                  sorteable: true,  width: '80'},
        {name: 'Valor',        prop: 'valor_a_pagar',           sorteable: true,  width: '150', derecha: true},
        {name: 'Estado',       prop: 'estado',                  sorteable: true,  width: '100'},
        {name: 'Origen',       prop: 'cdo_origen',              sorteable: true,  width: '120'},
        {name: 'Fecha Cargue', prop: 'fecha_creacion',          sorteable: false, width: '170'},
        {name: 'Responsable',  prop: 'cdo_usuario_responsable', sorteable: true,  width: '200'},
    ];

    private modalEventos : any;

    public tablaDatosParametricosValidacion : string = 'pry_datos_parametricos_validacion';
    public mostrarComboValorCampoValidacion : boolean = false;
    public arrCamposValidacion              : Array<Object> = [];
    public arrValoresCamposValidacion       : Array<Object> = [];

    public usuario       : any;
    public _grupo_trabajo: any;
    
    /**
     * Crea una instancia de ValidacionDocumentosComponent.
     * 
     * @param {Auth} _auth
     * @param {FormBuilder} fb
     * @param {Router} _router
     * @param {MatDialog} modal
     * @param {BaseService} _baseService
     * @param {CommonsService} _commonsService
     * @param {JwtHelperService} _jwtHelperService
     * @param {ConfiguracionService} _configuracionService
     * @param {ReportesBackgroundService} _reportesBackgroundService
     * @param {DocumentosRecibidosService} _documentosRecibidosService
     * @param {DatosParametricosValidacionService} _datosParametricosService
     * @memberof ValidacionDocumentosComponent
     */
    constructor(
        public _auth                       : Auth,
        private fb                         : FormBuilder,
        private _router                    : Router,
        private modal                      : MatDialog,
        private _baseService               : BaseService,
        private _commonsService            : CommonsService,
        private _jwtHelperService          : JwtHelperService,
        private _configuracionService      : ConfiguracionService,
        private _reportesBackgroundService : ReportesBackgroundService,
        private _documentosRecibidosService: DocumentosRecibidosService,
        private _datosParametricosService  : DatosParametricosValidacionService,
    ) {
        super();
        this.rows              = [];
        this.parameters        = new Parameters();
        this.aclsUsuario       = this._auth.getAcls();
        this.usuario           = this._jwtHelperService.decodeToken();
        this._grupo_trabajo    = this.usuario.grupos_trabajo.singular;
        this.trackingRecepcionInterface = this;
        this.init();
    }

    /**
     * OnInit del componente.
     *
     * @memberof ValidacionDocumentosComponent
     */
    ngOnInit() {
        this.pro_id.disable();

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionValidacionDocumentosAsignar')) {
            this.accionesBloque.push(
                { id: 'asignar', nombre: 'Asignar' }
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionValidacionDocumentosLiberar')) {
            this.accionesBloque.push(
                { id: 'liberar', nombre: 'Liberar' }
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionValidacionDocumentosValidar')) {
            this.accionesBloque.push(
                { id: 'validar', nombre: 'Validar' }
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionValidacionDocumentosRechazar')) {
            this.accionesBloque.push(
                {id: 'rechazar', nombre: 'Rechazar'}
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionValidacionDocumentosPagar')) {
            this.accionesBloque.push(
                {id: 'pagar', nombre: 'Pagar'}
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionDatosValidacion')) {
            this.accionesBloque.push(
                {id: 'datos_validacion', nombre: 'Datos Validación'}
            );
        }

        if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, 'RecepcionValidacionDocumentosValidar')) {
            this.accionesBloque.push(
                { id: 'datos_aprobacion', nombre: 'Datos Aprobación' }
            );
        }

        this.cargarOfes();
        this.setearControlUsuarios();
        this.estado_validacion.setValue(['pendiente']);
    }

    /**
     * Inicializa el formulario del componente.
     *
     * @private
     * @memberof ValidacionDocumentosComponent
     */
    private init() {
        this.initDataSort('cdo_fecha');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.form = this.fb.group({
            ofe_id                         : this.requerido(),
            cdo_fecha_validacion_dian_desde: [''],
            cdo_fecha_validacion_dian_hasta: [''],
            cdo_fecha_desde                : this.requerido(),
            cdo_fecha_hasta                : this.requerido(),
            pro_id                         : [''],
            cdo_origen                     : [''],
            cdo_usuario_responsable        : [''],
            usu_identificacion_nombre      : [''],
            rfa_prefijo                    : [''],
            cdo_consecutivo                : [''],
            cdo_clasificacion              : [''],
            estado_validacion              : [''],
            campo_validacion               : [''],
            valor_campo_validacion         : [''],
            filtro_grupos_trabajo_usuario  : [''],
            estado                         : ['']
        });

        this.ofe_id                          = this.form.controls['ofe_id'];
        this.cdo_fecha_validacion_dian_desde = this.form.controls['cdo_fecha_validacion_dian_desde'];
        this.cdo_fecha_validacion_dian_hasta = this.form.controls['cdo_fecha_validacion_dian_hasta'];
        this.cdo_fecha_desde                 = this.form.controls['cdo_fecha_desde'];
        this.cdo_fecha_hasta                 = this.form.controls['cdo_fecha_hasta'];
        this.pro_id                          = this.form.controls['pro_id'];
        this.cdo_origen                      = this.form.controls['cdo_origen'];
        this.cdo_usuario_responsable         = this.form.controls['cdo_usuario_responsable'];
        this.usu_identificacion_nombre       = this.form.controls['usu_identificacion_nombre'];
        this.rfa_prefijo                     = this.form.controls['rfa_prefijo'];
        this.cdo_consecutivo                 = this.form.controls['cdo_consecutivo'];
        this.cdo_clasificacion               = this.form.controls['cdo_clasificacion'];
        this.estado_validacion               = this.form.controls['estado_validacion'];
        this.campo_validacion                = this.form.controls['campo_validacion'];
        this.valor_campo_validacion          = this.form.controls['valor_campo_validacion'];
        this.filtro_grupos_trabajo_usuario   = this.form.controls['filtro_grupos_trabajo_usuario'];
        this.estado                          = this.form.controls['estado'];
    }

    /**
     * Crea un JSON con los parámetros de búsqueda.
     *
     * @return {Parameters} 
     * @memberof ValidacionDocumentosComponent
     */
    public getSearchParametersObject(excel: boolean = false): Parameters {
        this.parameters.length = 1;
        this.parameters.columnaOrden = this.columnaOrden;
        this.parameters.ordenDireccion = this.ordenDireccion;

        const fecha_envio_desde = this.cdo_fecha_validacion_dian_desde && this.cdo_fecha_validacion_dian_desde.value !== null && this.cdo_fecha_validacion_dian_desde.value !== '' ? String(moment(this.cdo_fecha_validacion_dian_desde.value).format('YYYY-MM-DD')) : '';
        const fecha_envio_hasta = this.cdo_fecha_validacion_dian_hasta && this.cdo_fecha_validacion_dian_hasta.value !== null && this.cdo_fecha_validacion_dian_hasta.value !== '' ? String(moment(this.cdo_fecha_validacion_dian_hasta.value).format('YYYY-MM-DD')) : '';
        const fecha_desde = this.cdo_fecha_desde && this.cdo_fecha_desde.value !== '' && this.cdo_fecha_desde.value != undefined ? String(moment(this.cdo_fecha_desde.value).format('YYYY-MM-DD')) : '';
        const fecha_hasta = this.cdo_fecha_hasta && this.cdo_fecha_hasta.value !== '' && this.cdo_fecha_hasta.value != undefined ? String(moment(this.cdo_fecha_hasta.value).format('YYYY-MM-DD')) : '';

        this.parameters.cdo_fecha_desde = fecha_desde;
        this.parameters.cdo_fecha_hasta = fecha_hasta;

        if (fecha_envio_desde)
            this.parameters.cdo_fecha_validacion_dian_desde = fecha_envio_desde;
        else
            delete this.parameters.cdo_fecha_validacion_dian_desde;

        if (fecha_envio_hasta)
            this.parameters.cdo_fecha_validacion_dian_hasta = fecha_envio_hasta;
        else
            delete this.parameters.cdo_fecha_validacion_dian_desde;

        if (this.pro_id && this.pro_id.value)
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

        if(this.estado_validacion && this.estado_validacion.value)
            this.parameters.estado_validacion = this.estado_validacion.value;
        else 
            delete this.parameters.estado_validacion; 

        if (this.rfa_prefijo && this.rfa_prefijo.value.trim())
            this.parameters.rfa_prefijo = this.rfa_prefijo.value;
        else
            delete this.parameters.rfa_prefijo;

        if (this.cdo_usuario_responsable && this.cdo_usuario_responsable.value)
            this.parameters.cdo_usuario_responsable = this.cdo_usuario_responsable.value;
        else
            delete this.parameters.cdo_usuario_responsable;

        if(this.campo_validacion && this.campo_validacion.value)
            this.parameters.campo_validacion = this.campo_validacion.value;
        else 
            delete this.parameters.campo_validacion; 

        if(this.valor_campo_validacion && this.valor_campo_validacion.value)
            this.parameters.valor_campo_validacion = this.valor_campo_validacion.value;
        else 
            delete this.parameters.valor_campo_validacion; 

        if(this.filtro_grupos_trabajo_usuario && this.filtro_grupos_trabajo_usuario.value)
            this.parameters.filtro_grupos_trabajo_usuario = this.filtro_grupos_trabajo_usuario.value;
        else 
            delete this.parameters.filtro_grupos_trabajo_usuario; 

        if(this.estado && this.estado.value)
            this.parameters.estado = this.estado.value;
        else 
            delete this.parameters.estado; 

        if (excel)
            this.parameters.excel = true;
        else
            delete this.parameters.excel;

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
     * Se encarga de traer la data para la validación de los documentos.
     *
     * @memberof ValidacionDocumentosComponent
     */
    public loadValidacionDocumentos(): void {
        this.loading(true);
        const parameters = this.getSearchParametersObject();
        this._documentosRecibidosService.listarValidacionDocumentos(parameters).subscribe({
            next: res => {
                this.loading(false);
                this.registros    = [];
                this.linkAnterior  = res.pag_anterior ? res.pag_anterior : '';
                this.linkSiguiente = res.pag_siguiente ? res.pag_siguiente : '';

                res.data.forEach(reg => {
                    let proveedor = reg.get_configuracion_proveedor ? reg.get_configuracion_proveedor.pro_razon_social : '';

                    if (proveedor === '' && reg.get_configuracion_proveedor)
                        proveedor = reg.get_configuracion_proveedor.pro_primer_nombre + ' ' + reg.get_configuracion_proveedor.pro_primer_apellido;

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
                            'cdo_id'                                       : reg.cdo_id,
                            'cdo_consecutivo'                              : reg.cdo_consecutivo,
                            'rfa_prefijo'                                  : reg.rfa_prefijo ? reg.rfa_prefijo : '',
                            'ofe_id'                                       : reg.ofe_id,
                            'ofe_identificacion'                           : reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion,
                            'pro_id'                                       : reg.pro_id,
                            'cdo_clasificacion'                            : reg.cdo_clasificacion,
                            'cdo_fecha_validacion_dian'                    : reg.cdo_fecha_validacion_dian,
                            'documento'                                    : (reg.rfa_prefijo ? reg.rfa_prefijo : '') + ' ' + reg.cdo_consecutivo,
                            'proveedor'                                    : proveedor,
                            'pro_identificacion'                           : reg.get_configuracion_proveedor.pro_identificacion,
                            'fecha'                                        : reg.cdo_fecha + ' ' + (reg.cdo_hora ? reg.cdo_hora : ''),
                            'moneda'                                       : moneda,
                            'valor_a_pagar'                                : reg.cdo_valor_a_pagar,
                            'cdo_origen'                                   : reg.cdo_origen,
                            'grupo_trabajo'                                : grupoTrabajo ?? '',
                            'estado_validacion'                            : reg.estado_validacion,
                            'cdo_usuario_responsable'                      : reg.cdo_usuario_responsable,
                            'get_validacion_ultimo'                        : reg.get_validacion_ultimo,
                            'estado'                                       : reg.estado,
                            'fecha_creacion'                               : reg.fecha_creacion,
                            'aplica_documento_anexo'                       : reg.aplica_documento_anexo,
                            'estado_validacion_validado'                   : reg.get_estado_validacion_validado,
                            'estado_validacion_en_proceso_pendiente'       : reg.get_estado_validacion_en_proceso_pendiente,
                            'ultimo_estado_validacion_en_proceso_pendiente': reg.get_ultimo_estado_validacion_en_proceso_pendiente,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Documentos', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            }
        });
    }

    /**
     * Carga los OFEs en el select de Receptores.
     *
     * @private
     * @memberof ValidacionDocumentosComponent
     */
    private cargarOfes() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false').subscribe({
            next: result => {
                this.loading(false);
                this.ofes = [];
                result.data.ofes.forEach(ofe => {
                    if(ofe.ofe_recepcion === 'SI' && ofe.ofe_recepcion_fnc_activo === 'SI') {
                        ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                        this.ofes.push(ofe);
                    }
                });
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
     * @memberof ValidacionDocumentosComponent
     */
    searchDocumentos(): void {
        this.loading(true);
        if (this.form.valid) {
            this.onPage();
            this.documentosTrackingRecepcion.tracking.offset = 0;
        } else {
            this.loading(false);
        }
    }

    /**
     *  Gestiona el evento de paginacion de la grid.
     *
     * @param {string} page Información del evento
     * @memberof ValidacionDocumentosComponent
     */
    public onPage(page: string = '') {
        this.paginador = page;
        this.selected = [];
        this.getData();
    }

    /**
     * Efectua la carga de datos.
     *
     * @memberof ValidacionDocumentosComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadValidacionDocumentos();
    }

    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     *
     * @param {number} size Cantidad de registros
     * @memberof ValidacionDocumentosComponent
     */
    onChangeSizePage(size: number) {
        this.length = size;
        this.getData();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los registros
     * @param {string} $order Dirección del orden de los registros [ASC - DESC]
     * @memberof ValidacionDocumentosComponent
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
        this.getData();
    }

    /**
     * Responde a las diferentes acciones del combo de Acciones en Bloque.
     *
     * @param {string} selectedOption Acción en bloque seleccionada
     * @memberof ValidacionDocumentosComponent
     */
    accionesEnBloque(selectedOption: string) {
        let ids            : string  = '';
        let docs           : string  = '';
        let registros      : any     = {lote: '', documentos: []};
        let permitirAccion : boolean = true;
        let estadoValidacionEnProcesoPendiente: any;
        let estadoValidacionValidado: any;

        if (this.selected.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Acciones en Bloque', 'Ok, entiendo', 'btn btn-warning');
        } else {
            if (selectedOption == 'asignar' || selectedOption == 'liberar') {
                this.loading(true);
                this.selected.forEach(reg => {
                    registros.documentos.push(reg.cdo_id);
                });

                this._documentosRecibidosService.crearEstadoValidacionDocumentosRecibidos(this.ofe_id.value, registros.documentos.join(','), '', selectedOption).subscribe({
                    next: (res) => {
                        this.loading(false);
                        this.getData();
                        let mensajeAdicional = '';
                        if(res.errors) {
                            if (Array.isArray(res.errors) && res.errors.length > 0) {
                                res.errors.forEach(strResultado => {
                                    mensajeAdicional += '<li>' + strResultado + '</li>';
                                });
                            }
                        }
                        this.showSuccess(res.message + (mensajeAdicional ? '<span style="text-align:left; font-weight: bold;"><ul>' + mensajeAdicional + '</ul></span>' : ''), 'success', 'Validación Documentos', 'Ok', 'btn btn-success');
                    },
                    error: (error) => {
                        this.loading(false);
                        const texto_errores = this.parseError(error);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al intentar Enviar a Validación', 'Ok', 'btn btn-danger');
                    }
                });
            } else {
                const docsEnviarValidacion      : string[] = [];
                const docsSinValidacionRechazado: string[] = [];

                this.selected.forEach(reg => {
                    ids += reg.cdo_id + '|';
                    docs += reg.cdo_consecutivo + ', ';
                    docsEnviarValidacion.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                    registros.documentos.push(reg.cdo_id);

                    // Debe verificar que cada documento tenga como último estado VALIDACION PENDIENTE y haya seleccionado validar o rechazar
                    if(!this.checkEstadoValidacionPendiente(reg) && (selectedOption === 'validar' || selectedOption === 'rechazar')) {
                        if(permitirAccion)
                            permitirAccion = false;

                        docsSinValidacionRechazado.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                    }

                    // Debe verificar que cada documento tenga como último estado VALIDACION VALIDADO y haya seleccionado pagar o datos_aprobacion
                    if(!this.checkEstadoValidacionValidado(reg) && (selectedOption === 'pagar' || selectedOption === 'datos_aprobacion')) {
                        if(permitirAccion)
                            permitirAccion = false;

                        docsSinValidacionRechazado.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                    }

                    // Debe verificar que cada documento tenga como último estado VALIDACION PENDIENTE o RECHAZADO y haya seleccionado datos_validacion
                    if((!this.checkEstadoValidacionPendiente(reg) && !this.checkEstadoValidacionRechazado(reg)) && selectedOption === 'datos_validacion') {
                        if(permitirAccion)
                            permitirAccion = false;

                        docsSinValidacionRechazado.push(reg.rfa_prefijo + " " + reg.cdo_consecutivo);
                    }

                    if(estadoValidacionEnProcesoPendiente === undefined)
                        estadoValidacionEnProcesoPendiente = reg.estado_validacion_en_proceso_pendiente;

                    if(estadoValidacionValidado === undefined)
                        estadoValidacionValidado = reg.estado_validacion_validado;
                });

                if (registros.documentos.length == 0) {
                    this.showError('<h3>Debe seleccionar uno o más documentos.</h3>', 'warning', 'Validación Documentos', 'Ok, entiendo', 'btn btn-warning');
                    return false;
                } else if (!permitirAccion) {
                    let accionValidacion;

                    switch(selectedOption) {
                        case 'datos_aprobacion':
                        case 'pagar':
                            accionValidacion = 'VALIDADO';
                            break;
                        case 'validar':
                        case 'rechazar':
                            accionValidacion = 'PENDIENTE';
                            break;
                        case 'datos_validacion':
                            accionValidacion = 'PENDIENTE o RECHAZADO';
                            break;
                    }

                    this.showError('<h3>Los siguientes documentos <strong>NO</strong> tienen como último estado <strong>VALIDACIÓN '+ accionValidacion +'</strong>: [' + (docsSinValidacionRechazado.join(', ')) + ']</h3>', 'warning', 'Validación Documentos', 'Ok, entiendo', 'btn btn-warning');
                    return false;
                } else {
                    let dataDocumentos: DataDocumentosValidacion = {
                        'ofe_id'                                : this.ofe_id.value,
                        'cdo_ids'                               : registros.documentos.join(','),
                        'documentos_procesar'                   : docsEnviarValidacion,
                        'estado_validacion_en_proceso_pendiente': estadoValidacionEnProcesoPendiente,
                        'estado_validacion_validado'            : estadoValidacionValidado
                    };

                    this.openModalEventosDianDocumentos(selectedOption, dataDocumentos);
                }
            }
        }
    }

    /**
     * Determina si el documento cuenta con el estado VALIDACION PENDIENTE.
     *
     * @param {*} data Documento a validar el estado
     * @return {boolean} 
     * @memberof ValidacionDocumentosComponent
     */
    checkEstadoValidacionPendiente(data): boolean {
        if(data.get_validacion_ultimo.est_estado === 'VALIDACION' && data.get_validacion_ultimo.est_resultado === 'PENDIENTE')
            return true;

        return false;
    }

    /**
     * Determina si el documento cuenta con el estado VALIDACION VALIDADO.
     *
     * @param {*} data Documento a validar el estado
     * @return {boolean} 
     * @memberof ValidacionDocumentosComponent
     */
    checkEstadoValidacionValidado(data): boolean {
        if(data.get_validacion_ultimo.est_estado === 'VALIDACION' && data.get_validacion_ultimo.est_resultado === 'VALIDADO')
            return true;

        return false;
    }

    /**
     * Determina si el documento cuenta con el estado VALIDACION RECHAZADO.
     *
     * @param {*} data Documento a validar el estado
     * @return {boolean} 
     * @memberof ValidacionDocumentosComponent
     */
    checkEstadoValidacionRechazado(data): boolean {
        if(data.get_validacion_ultimo.est_estado === 'VALIDACION' && data.get_validacion_ultimo.est_resultado === 'RECHAZADO')
            return true;

        return false;
    }

    /**
     * Apertura una ventana modal para eventos DIAN.
     *
     * @param {string} selectedOption Acción en bloque seleccionada
     * @param {DataDocumentosValidacion} data Objeto con la información de los documentos
     * @memberof ValidacionDocumentosComponent
     */
    public openModalEventosDianDocumentos(selectedOption: string, data: DataDocumentosValidacion): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '500px';
        modalConfig.data = {
            documentos                   : data,
            selectedOption               : selectedOption,
            parent                       : this,
            origen                       : 'validacion_documentos',
            ofeRecepcionFncActivo        : this.ofeRecepcionFncActivo,
            ofeRecepcionFncConfiguracion : this.ofeRecepcionFncConfiguracion
        };
        this.modalEventos = this.modal.open(ModalEventosDocumentosComponent, modalConfig);
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     *
     * @param {*} opcion Acción en bloque a ejecutar
     * @param {any[]} selected Registros seleccionados
     * @memberof ValidacionDocumentosComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción de los botones de opciones de un registro.
     *
     * @param {*} item Registro seleccionado
     * @param {string} opcion Acción a realizar
     * @memberof ValidacionDocumentosComponent
     */
    onOptionItem(item: any, opcion: string) {}

    /**
     * Gestiona la acción del botón de descarga de documentos.
     *
     * @param {any[]} selected Registros seleccionados
     * @param {string[]} tipos Tipo de descarga
     * @memberof ValidacionDocumentosComponent
     */
    onDescargarItems(selected: any[], tipos: string[]) {
        this.selected = selected;
        this.downloadDocs(tipos);
    }

    /**
     * Gestiona la acción del botón de envío por correo de documentos.
     *
     * @param {any[]} selected Registros seleccionados
     * @param {*} tipos Tipo de envió
     * @memberof ValidacionDocumentosComponent
     */
    onEnviarItems(selected: any[], tipos) {}

    /**
     * Gestiona la acción del botón de descargar Excel.
     *
     * @memberof ValidacionDocumentosComponent
     */
    onDescargarExcel() {
        if (this.existeConsulta) {
            this.loading(true);
            this._documentosRecibidosService.descargarExcel(this.getSearchParametersObject(true), true).subscribe({
                next: (response => {
                    this.loading(false);
                }),
                error: (error => {
                    this.loading(false);
                    this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar excel de Validación de Documentos', 'OK', 'btn btn-danger');
                })
            });
        } else {
            this.showError('<h3>Debe existir una consulta con registros listados</h3>', 'warning', 'Error al descargar excel', 'OK', 'btn btn-warning');
        }
    }

    /**
     * Ejecuta la petición para agendar el proceso de descarga del Excel en background.
     *
     * @memberof ValidacionDocumentosComponent
     */
    onAgendarReporteBackground() {
        if (this.existeConsulta) {
            this.loading(true);

            let params = {
                tipo : 'recepcion-validacion',
                json : JSON.stringify(this.getSearchParametersObject(true))
            };

            this._reportesBackgroundService.agendarReporteExcel(params).subscribe({
                next: (response => {
                    this.loading(false);
                    swal({
                        type : 'success',
                        title: 'Proceso Exitoso',
                        html : response.message
                    })
                    .catch(swal.noop);
                }),
                error: (error => {
                    this.loading(false);
                    this.showError('<h3>Error al agendar</h3><p>No fue posible agendar el reporte en background.</p>', 'error', 'Error en la petición', 'OK', 'btn btn-danger');
                })
            });
        } else {
            this.showError('<h3>Debe existir una consulta con registros listados</h3>', 'warning', 'Error al descargar excel', 'OK', 'btn btn-warning');
        }
    }

    /**
     * Monitoriza cuando el valor del select de OFEs cambia para realizar acciones determinadas de acuerdo al OFE.
     * 
     * @param {object} ofe Objeto con la información del OFE seleccionado
     * @memberof ValidacionDocumentosComponent
     */
    ofeHasChanged(ofe) {
        this.arrCamposValidacion   = [];
        this.campo_validacion.setValue('');
        this.valor_campo_validacion.setValue('');
        this.filtro_grupos_trabajo_usuario.setValue('');
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

        if(ofe.get_grupos_trabajo && ofe.get_grupos_trabajo.length > 0) {
            this.mostrarFiltroGruposTrabajo = true;
            this.columns.push({name: this._grupo_trabajo, prop: 'grupo_trabajo', sorteable: true, width: '200'});

            this.obtenerGruposTrabajoUsuario('validador');
        } else {
            this.mostrarFiltroGruposTrabajo = false;
            this.columns.forEach((columna: any, indice: number) => {
                if(columna.prop && columna.prop === 'grupo_trabajo')
                    this.columns.splice(indice, 1);
            });
        }
    }

    /**
     * Permite descargar XML y PDF de los documentos de validación.
     *
     * @param {*} tipos Tipos de descarga
     * @memberof ValidacionDocumentosComponent
     */
    downloadDocs(tipos: any) {
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
                switchMap(term => this._configuracionService.searchUsuariosGestorValidador('validador', term).pipe(
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
        this.selectUsuarios.items = [];
        this.usuariosInput$.next('');
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
     * @memberof ValidacionDocumentosComponent
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
     * @memberof ValidacionDocumentosComponent
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