import {Component, OnDestroy, OnInit, ViewChild, AfterViewInit} from '@angular/core';
import {BaseComponentView} from '../../../core/base_component_view';
import {ActivatedRoute, Router} from '@angular/router';
import {AbstractControl, FormArray, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {CommonsService} from '../../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {MatAccordion} from '@angular/material/expansion';
import {JwtHelperService} from '@auth0/angular-jwt';
import {Auth} from '../../../../services/auth/auth.service';
import {DatosGeneralesRegistroComponent} from '../../../commons/datos-generales-registro/datos-generales-registro.component';
import {concat, Observable, of, Subject} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, switchMap, tap} from 'rxjs/operators';

@Component({
    selector: 'app-proveedores-gestionar',
    templateUrl: './proveedores-gestionar.component.html',
    styleUrls: ['./proveedores-gestionar.component.scss']
})
export class ProveedoresGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion', {static: false}) acordion: MatAccordion;
    @ViewChild('DatosGenerales', {static: true}) datosGeneralesControl: DatosGeneralesRegistroComponent;

    // Usuario en línea
    public usuario: any;
    public objMagic = {};
    public ver: boolean;
    public editar: boolean;
    // Formulario y controles
    public gestor: ProveedoresGestionarComponent;
    public formulario: FormGroup;
    public DV: AbstractControl;
    public pro_id: AbstractControl;
    public pro_identificacion: AbstractControl;
    public pro_id_personalizado: AbstractControl;
    public pro_razon_social: AbstractControl;
    public pro_nombre_comercial: AbstractControl;
    public pro_primer_apellido: AbstractControl;
    public pro_segundo_apellido: AbstractControl;
    public pro_primer_nombre: AbstractControl;
    public pro_otros_nombres: AbstractControl;
    public tdo_id: AbstractControl;
    public tat_id: AbstractControl;
    public toj_id: AbstractControl;
    public ofe_id: AbstractControl;
    public pai_id: AbstractControl;
    public dep_id: AbstractControl;
    public mun_id: AbstractControl;
    public cpo_id: AbstractControl;
    public pro_direccion: AbstractControl;
    public pro_telefono: AbstractControl;
    public pai_id_domicilio_fiscal: AbstractControl;
    public dep_id_domicilio_fiscal: AbstractControl;
    public mun_id_domicilio_fiscal: AbstractControl;
    public cpo_id_domicilio_fiscal: AbstractControl;
    public pro_direccion_domicilio_fiscal: AbstractControl;
    public pro_correo: AbstractControl;
    public rfi_id: AbstractControl;
    public ref_id: AbstractControl;
    public pro_matricula_mercantil: AbstractControl;
    public pro_correos_notificacion: AbstractControl;
    public pro_usuarios_recepcion: AbstractControl;
    public pro_integracion_erp: AbstractControl;
    public estado: AbstractControl;

    public tojSeleccionado = '';
    public titulo: string;
    public initDV = null;

    public paisObjeto = {};
    public departamentoObjeto = {};
    public municipioObjeto = {};
    public paisFiscalObjeto = {};
    public departamentoFiscalObjeto = {};
    public municipioFiscalObjeto = {};
    public responsabilidadFiscalObjeto = {};
    public codigoPostalObjeto = {};
    public codigoPostalFiscalObjeto = {};
    public tatObjeto = {};
    public codigoNIT = '31';
    proveedores: FormArray;
    motivos: FormArray;
    observaciones: FormArray;
    filtros: FormArray;
    public aclsUsuario: any;

    recurso = 'Proveedor';

    //Datos asociados al OFE DHLEXPRESS
    public ofeDHL: boolean;

    _pro_id: any;
    _pro_identificacion: any;
    _ofe_identificacion: string;
    ultimoComprobado: string;
    ultimoOfeComprobado: string;

    public ofes: Array<any> = [];
    public paises: Array<any> = [];
    public tipoDocumentos: Array<any> = [];
    public tipoOrganizaciones: Array<any> = [];
    public tipoRegimen: Array<any> = [];
    public responsabilidadesFiscales: Array<any> = [];
    public ResFiscal: Array<any> = [];
    public tiemposAceptacionTacita  : Array<any> = [];
    public tipoDocumentoSelect      : any = {};
    public tipoOrganizacionSelect   : any = {};
    public regimenFiscalSelect      : any = {};
    public responsabilidadesFiscalesSelect: any = [];

    usuarios$: Observable<string[]>;
    usuariosLoading: boolean = false;
    usuariosInput$ = new Subject<string>();
    selectedUsuarios: any;

    datosGenerales: FormGroup;
    ofeSelector: FormGroup;
    ubicacion: FormGroup;
    ubicacionFiscal: FormGroup;
    datosTributarios: FormGroup;
    informacionAdicional: FormGroup;
    usuariosRecepcion: FormGroup;
    correosNotificacion: FormGroup;
    reglaAceptacionTacita: FormGroup;
    reglaIntegracionErp: FormGroup;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    public maxlengthIdentificacion: number = 20;
    public regexIdentificacion: RegExp = /^[0-9a-zA-Z-]{1,20}$/;

    /**
     * Constructor
     * @param _auth
     * @param _router
     * @param _route
     * @param _formBuilder
     * @param _commonsService
     * @param _configuracionService
     * @param jwtHelperService
     */
    constructor(
        private _auth: Auth,
        private _router: Router,
        private _route: ActivatedRoute,
        private _formBuilder: FormBuilder,
        private _commonsService: CommonsService,
        private _configuracionService: ConfiguracionService,
        private jwtHelperService: JwtHelperService
    ) {
        super();
        this._configuracionService.setSlug = 'proveedores';
        this.gestor = this;
        this.init();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    ngOnInit() {
        this._pro_id = this._route.snapshot.params['pro_id'];
        this._pro_identificacion = this._route.snapshot.params['pro_identificacion'];
        this._ofe_identificacion = this._route.snapshot.params['ofe_identificacion'];
        this.ver = false;
        if (this._pro_identificacion && !this._pro_id) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
        } else if (this._pro_identificacion && this._pro_id) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true;
        } else {
            this.titulo = 'Crear ' + this.recurso;
        }

        this.initForBuild();

        this.usuarios$ = concat(
            of([]), // default items
            this.usuariosInput$.pipe(
                debounceTime(750),
                distinctUntilChanged((prev, curr) => prev === curr || curr === null || curr === undefined),
                tap(() => {
                    this.loading(true);
                    this.usuariosRecepcion.get('pro_usuarios_recepcion').disable({onlySelf: true});
                }),
                switchMap(term => this._configuracionService.getPredictiveUsers(this.ofe_id.value, term).pipe(
                    catchError(() => of([])),
                    tap(() => {
                        this.loading(false);
                        this.usuariosRecepcion.get('pro_usuarios_recepcion').enable({onlySelf: true});
                    })
                ))
            ));
    }

    /**
     * Vista construida
     */
    ngAfterViewInit() {
        if (this.ver) {
            this.acordion.openAll();
        }
        if (this.toj_id.value && (this.editar || this.ver )) {
            this.datosGeneralesControl.switchControles(this.toj_id.value);
        }
    }

    /**
     * On destroy.
     *
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next('');
        this._unsubscribeAll.complete();
    }

    /**
     * Inicializacion de los diferentes controles.
     *
     */
    init() {
        this.aclsUsuario = this._auth.getAcls();
        this.buildFormulario();
    }

    /**
     * Se encarga de cargar los datos de un proveedor que se ha seleccionado en el tracking.
     *
     */
    public loadProveedor(): void {
        this.loading(true);
        this._configuracionService.getProv(this._pro_identificacion, this._ofe_identificacion).subscribe(
            res => {
                if (res) {
                    if (res.data)
                        res = res.data;
                    if (res.get_configuracion_obligado_facturar_electronicamente) {
                        this.ofe_id.setValue(res.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion);
                        //Si el nit es DHLEXPRESS debe mostarse la seccion de integracion con bassware
                        this.ofeDHL = false;
                        if (res.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion == '860502609' || res.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion == '830076778')
                            this.ofeDHL = true;
                    }
                    if (res.get_parametro_tipo_documento) {
                        this.tdo_id.setValue(res.get_parametro_tipo_documento.tdo_codigo);
                        this.tipoDocumentoSelect = res.get_parametro_tipo_documento;
                    }
                    if (res.get_parametro_tipo_organizacion_juridica) {
                        this.toj_id.setValue(res.get_parametro_tipo_organizacion_juridica.toj_codigo);
                        this.tipoOrganizacionSelect = res.get_parametro_tipo_organizacion_juridica;
                    }
                    if (res.get_regimen_fiscal) {
                        this.rfi_id.setValue(res.get_regimen_fiscal.rfi_codigo);
                        this.regimenFiscalSelect = res.get_regimen_fiscal;
                    } else {
                        this.regimenFiscalSelect = this.tipoRegimen;
                    }
                    if (res.get_responsabilidad_fiscal) {
                        this.responsabilidadesFiscalesSelect = res.get_responsabilidad_fiscal;
                    } else {
                        this.responsabilidadesFiscalesSelect = this.responsabilidadesFiscales;
                    }
                    if (res.ref_id && res.get_responsabilidad_fiscal) {
                        const arrRefId = res.ref_id ? res.ref_id.split(';') : [];
                        const arrResponsabilidadFiscal = [];
                        arrRefId.forEach(element => {
                            const resultado = res.get_responsabilidad_fiscal.find(valor => valor.ref_codigo === element && valor.estado === 'ACTIVO');

                            if (resultado !== undefined)
                                arrResponsabilidadFiscal.push(element);
                        });

                        if (!this.ver) {
                            this.ref_id.setValue(arrResponsabilidadFiscal);
                        } else {
                            this.ref_id.setValue(arrRefId);
                        }
                    }

                    if (res.pro_usuarios_recepcion !== null && res.pro_usuarios_recepcion !== undefined && res.pro_usuarios_recepcion !== '') {
                        /* let arrUsuariosRecepcion: any[] = [];
                        res.pro_usuarios_recepcion.forEach(usuarioRecepcion => {
                            arrUsuariosRecepcion.push(usuarioRecepcion.usu_id);
                        }); */
                        this.selectedUsuarios = JSON.parse(res.usuarios_recepcion);
                        this.usuariosRecepcion.controls['pro_usuarios_recepcion'].setValue(JSON.parse(res.usuarios_recepcion));
                    }
                    
                    this.pro_identificacion.setValue(res.pro_identificacion);
                    this.pro_id_personalizado.setValue(res.pro_id_personalizado);
                    this.pro_razon_social.setValue((res.pro_razon_social !== null) ? res.pro_razon_social : '');
                    this.pro_nombre_comercial.setValue((res.pro_nombre_comercial !== null) ? res.pro_nombre_comercial : '');
                    this.pro_primer_apellido.setValue((res.pro_primer_apellido !== null) ? res.pro_primer_apellido : '');
                    this.pro_segundo_apellido.setValue((res.pro_segundo_apellido !== null) ? res.pro_segundo_apellido : '');
                    this.pro_primer_nombre.setValue((res.pro_primer_nombre !== null) ? res.pro_primer_nombre : '');
                    this.pro_otros_nombres.setValue((res.pro_otros_nombres !== null) ? res.pro_otros_nombres : '');
                    this.pro_direccion.setValue(res.pro_direccion);
                    this.pro_telefono.setValue(res.pro_telefono);
                    this.pro_correo.setValue(res.pro_correo);
                    this.pro_direccion_domicilio_fiscal.setValue(res.pro_direccion_domicilio_fiscal);
                    this.pro_matricula_mercantil.setValue(res.pro_matricula_mercantil);
                    this.pro_correos_notificacion.setValue(res.pro_correos_notificacion ? res.pro_correos_notificacion.split(',') : []);
                    
                    this.estado.setValue(res.estado);
                    if (res.get_parametro_pais) {
                        this.paisObjeto['pai_id'] = res.get_parametro_pais.pai_id;
                        this.paisObjeto['pai_codigo'] = res.get_parametro_pais.pai_codigo;
                        this.paisObjeto['pai_codigo_descripion'] = res.get_parametro_pais.pai_codigo + ' - ' + res.get_parametro_pais.pai_descripcion;
                    }
                    if (res.get_parametro_departamento) {
                        this.departamentoObjeto['dep_id'] = res.get_parametro_departamento.dep_id;
                        this.departamentoObjeto['dep_codigo'] = res.get_parametro_departamento.dep_codigo;
                        this.departamentoObjeto['dep_codigo_descripion'] = res.get_parametro_departamento.dep_codigo + ' - ' + res.get_parametro_departamento.dep_descripcion;
                    }
                    if (res.get_parametro_municipio) {
                        this.municipioObjeto['mun_id'] = res.get_parametro_municipio.mun_id;
                        this.municipioObjeto['mun_codigo'] = res.get_parametro_municipio.mun_codigo;
                        this.municipioObjeto['mun_codigo_descripion'] = res.get_parametro_municipio.mun_codigo + ' - ' + res.get_parametro_municipio.mun_descripcion;
                    }
                    if (res.get_parametro_domicilio_fiscal_pais) {
                        this.paisFiscalObjeto['pai_id'] = res.get_parametro_domicilio_fiscal_pais.pai_id;
                        this.paisFiscalObjeto['pai_codigo'] = res.get_parametro_domicilio_fiscal_pais.pai_codigo;
                        this.paisFiscalObjeto['pai_codigo_descripion'] = res.get_parametro_domicilio_fiscal_pais.pai_codigo + ' - ' + res.get_parametro_domicilio_fiscal_pais.pai_descripcion;
                    }
                    if (res.get_parametro_domicilio_fiscal_departamento) {
                        this.departamentoFiscalObjeto['dep_id'] = res.get_parametro_domicilio_fiscal_departamento.dep_id;
                        this.departamentoFiscalObjeto['dep_codigo'] = res.get_parametro_domicilio_fiscal_departamento.dep_codigo;
                        this.departamentoFiscalObjeto['dep_codigo_descripion'] = res.get_parametro_domicilio_fiscal_departamento.dep_codigo + ' - ' + res.get_parametro_domicilio_fiscal_departamento.dep_descripcion;
                    }
                    if (res.get_parametro_domicilio_fiscal_municipio) {
                        this.municipioFiscalObjeto['mun_id'] = res.get_parametro_domicilio_fiscal_municipio.mun_id;
                        this.municipioFiscalObjeto['mun_codigo'] = res.get_parametro_domicilio_fiscal_municipio.mun_codigo;
                        this.municipioFiscalObjeto['mun_codigo_descripion'] = res.get_parametro_domicilio_fiscal_municipio.mun_codigo + ' - ' + res.get_parametro_domicilio_fiscal_municipio.mun_descripcion;
                    }

                    if (res.get_codigo_postal) {
                        this.codigoPostalObjeto['cpo_id'] = res.get_codigo_postal.cpo_id;
                        this.codigoPostalObjeto['cpo_codigo'] = res.get_codigo_postal.cpo_codigo;
                    }
                    if (res.get_codigo_postal_domicilio_fiscal) {
                        this.codigoPostalFiscalObjeto['cpo_id'] = res.get_codigo_postal_domicilio_fiscal.cpo_id;
                        this.codigoPostalFiscalObjeto['cpo_codigo'] = res.get_codigo_postal_domicilio_fiscal.cpo_codigo;
                    }

                    if (res.get_tiempo_aceptacion_tacita){
                        this.tatObjeto['tat_id'] = res.get_tiempo_aceptacion_tacita.tat_id;
                        this.tatObjeto['tat_codigo'] = res.get_tiempo_aceptacion_tacita.tat_codigo;
                        this.tatObjeto['tat_codigo_descripcion'] = res.get_tiempo_aceptacion_tacita.tat_codigo + ' - ' + res.get_tiempo_aceptacion_tacita.tat_descripcion;
                    }

                    if (res.get_parametro_tipo_documento && res.get_parametro_tipo_documento.tdo_codigo === this.codigoNIT) {
                        if (res.pro_identificacion) {
                            this._commonsService.calcularDV(res.pro_identificacion).subscribe(
                                result => {
                                    if (result.data || result.data === 0) {
                                        this.DV.setValue(result.data);
                                    }
                                    else {
                                        this.DV.setValue(null);
                                    }
                                    this.loading(false);
                                });
                        }
                    } else {
                        this.initDV = false;
                        this.DV.setValue(null);
                        this.loading(false);
                    }

                    this.pai_id.setValue(this.paisObjeto);
                    this.dep_id.setValue(this.departamentoObjeto);
                    this.mun_id.setValue(this.municipioObjeto);
                    this.pai_id_domicilio_fiscal.setValue(this.paisFiscalObjeto);
                    this.dep_id_domicilio_fiscal.setValue(this.departamentoFiscalObjeto);
                    this.mun_id_domicilio_fiscal.setValue(this.municipioFiscalObjeto);
                    this.cpo_id.setValue(this.codigoPostalObjeto);
                    this.tat_id.setValue(this.tatObjeto);
                    this.pro_integracion_erp.setValue((res.pro_integracion_erp !== null && res.pro_integracion_erp !== undefined) ? res.pro_integracion_erp : 'NO');
                    this.cpo_id_domicilio_fiscal.setValue(this.codigoPostalFiscalObjeto);
                    this.objMagic['fecha_creacion'] = res.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.fecha_modificacion;
                    this.objMagic['estado'] = res.estado;
                }
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Proveedor', 'Ok', 'btn btn-danger', 'configuracion/proveedores', this._router);
            }
        );
    }

    /**
     * Construccion del formulario principal.
     *
     */
    buildFormulario() {
        this.formulario = this._formBuilder.group({
            DatosGenerales: this.getFormularioDatosGenerales(),
            ofeSelector: this.buildFormularioDatosProveedor(),
            Ubicacion: this.buildFormularioUbicacion(),
            UbicacionFiscal: this.buildFormularioUbicacionFiscal(),
            DatosTributarios: this.buildFormularioDatosTributarios(),
            InformacionAdicional: this.buildFormularioInformacionAdicional(),
            UsuariosRecepcion: this.buildFormularioUsuariosRecepcion(),
            CorreosNotificacion: this.buildFormularioNotificaciones(),
            reglaAceptacionTacita: this.buildFormularioDatosTat(),
            reglaIntegracionErp: this.buildFormularioDatosIntegracionErp()
        });
    }

    /**
     * Construccion del formulario de datos personales.
     *
     */
    getFormularioDatosGenerales() {
        this.datosGenerales = this._formBuilder.group({
            tdo_id: this.requerido(),
            toj_id: this.requerido(),
            pro_identificacion: this.requeridoMaxlong(20),
            pro_id_personalizado: [''],
            pro_razon_social: this.maxlong(255),
            pro_nombre_comercial: this.maxlong(255),
            pro_primer_apellido: this.maxlong(100),
            pro_segundo_apellido: [''],
            pro_primer_nombre: this.maxlong(100),
            pro_otros_nombres: [''],
            DV: [''],
            estado: ['']
        });
        this.tdo_id = this.datosGenerales.controls['tdo_id'];
        this.toj_id = this.datosGenerales.controls['toj_id'];
        this.pro_identificacion = this.datosGenerales.controls['pro_identificacion'];
        this.pro_id_personalizado = this.datosGenerales.controls['pro_id_personalizado'];
        this.pro_razon_social = this.datosGenerales.controls['pro_razon_social'];
        this.pro_nombre_comercial = this.datosGenerales.controls['pro_nombre_comercial'];
        this.pro_primer_apellido = this.datosGenerales.controls['pro_primer_apellido'];
        this.pro_segundo_apellido = this.datosGenerales.controls['pro_segundo_apellido'];
        this.pro_primer_nombre = this.datosGenerales.controls['pro_primer_nombre'];
        this.pro_otros_nombres = this.datosGenerales.controls['pro_otros_nombres'];
        this.DV = this.datosGenerales.controls['DV'];
        this.estado = this.datosGenerales.controls['estado'];
        return this.datosGenerales;
    }

    /**
     * Construccion de los datos del ofe.
     *
     */
    buildFormularioDatosProveedor() {
        this.ofeSelector = this._formBuilder.group({
            ofe_id: this.requerido(),
        });
        this.ofe_id = this.ofeSelector.controls['ofe_id'];
        return this.ofeSelector;
    }

    /**
     * Construccion de los datos de aceptacion tacita.
     *
     */
    buildFormularioDatosTat() {
        this.reglaAceptacionTacita = this._formBuilder.group({
            tat_id: [],
        });
        this.tat_id = this.reglaAceptacionTacita.controls['tat_id'];
        return this.reglaAceptacionTacita;
    }

    /**
     * Construccion de los datos de Transmision a ERP.
     *
     */
    buildFormularioDatosIntegracionErp() {
        this.reglaIntegracionErp = this._formBuilder.group({
            pro_integracion_erp: [''],
        });
        this.pro_integracion_erp = this.reglaIntegracionErp.controls['pro_integracion_erp'];
        return this.reglaIntegracionErp;
    }

    /**
     * Construcción del formulario de ubicacion.
     *
     */
    buildFormularioUbicacion() {
        this.ubicacion = this._formBuilder.group({
            pai_id: [''],
            dep_id: [''],
            mun_id: [''],
            pro_direccion: this.maxlong(255),
            pro_telefono: this.maxlong(255),
            cpo_id: this.maxlong(50)
        });
        this.pai_id = this.ubicacion.controls['pai_id'];
        this.dep_id = this.ubicacion.controls['dep_id'];
        this.mun_id = this.ubicacion.controls['mun_id'];
        this.pro_direccion = this.ubicacion.controls['pro_direccion'];
        this.pro_telefono = this.ubicacion.controls['pro_telefono'];
        this.cpo_id = this.ubicacion.controls['cpo_id'];
        return this.ubicacion;
    }

    /**
     * Construcción del formulario de domicilio fiscal.
     *
     */
    buildFormularioUbicacionFiscal() {
        this.ubicacionFiscal = this._formBuilder.group({
            pai_id_domicilio_fiscal: [''],
            dep_id_domicilio_fiscal: [''],
            mun_id_domicilio_fiscal: [''],
            cpo_id_domicilio_fiscal: this.maxlong(50),
            pro_direccion_domicilio_fiscal: this.maxlong(255)
        });
        this.pai_id_domicilio_fiscal = this.ubicacionFiscal.controls['pai_id_domicilio_fiscal'];
        this.dep_id_domicilio_fiscal = this.ubicacionFiscal.controls['dep_id_domicilio_fiscal'];
        this.mun_id_domicilio_fiscal = this.ubicacionFiscal.controls['mun_id_domicilio_fiscal'];
        this.pro_direccion_domicilio_fiscal = this.ubicacionFiscal.controls['pro_direccion_domicilio_fiscal'];
        this.cpo_id_domicilio_fiscal = this.ubicacionFiscal.controls['cpo_id_domicilio_fiscal'];
        return this.ubicacionFiscal;
    }

    /**
     * Construccion de los datos tributarios.
     *
     */
    buildFormularioDatosTributarios() {
        this.datosTributarios = this._formBuilder.group({
            rfi_id: [''],
            ref_id: [''],
        });
        this.rfi_id = this.datosTributarios.controls['rfi_id'];
        this.ref_id = this.datosTributarios.controls['ref_id'];
        return this.datosTributarios;
    }

    /**
     * Construcción del formulario de información adicional.
     *
     */
    buildFormularioInformacionAdicional() {
        this.informacionAdicional = this._formBuilder.group({
            pro_correo: ['', Validators.compose(
                [
                    Validators.email,
                    Validators.maxLength(255),
                    Validators.minLength(10)
                ],
            )],
            pro_matricula_mercantil: this.maxlong(100)
        });
        this.pro_correo = this.informacionAdicional.controls['pro_correo'];
        this.pro_matricula_mercantil = this.informacionAdicional.controls['pro_matricula_mercantil'];
        return this.informacionAdicional;
    }

    /**
     * Construcción del formulario de usuarios recepción.
     *
     */
    buildFormularioUsuariosRecepcion() {
        this.usuariosRecepcion = this._formBuilder.group({
            pro_usuarios_recepcion: []
        });
        this.pro_usuarios_recepcion = this.usuariosRecepcion.controls['pro_usuarios_recepcion'];
        return this.usuariosRecepcion;
    }

    /**
     * Construccion del formgroup para el campo de notificaciones.
     *
     */
    buildFormularioNotificaciones() {
        this.correosNotificacion = this._formBuilder.group({
            pro_correos_notificacion: ['']
        });
        this.pro_correos_notificacion = this.correosNotificacion.controls['pro_correos_notificacion'];
        return this.correosNotificacion;
    }  

    /**
     * Permite regresar a la lista de proveedores.
     *
     */
    regresar() {
        this._router.navigate(['configuracion/proveedores']);
    }

    /**
     * Inicializa la data necesaria para la construcción del proveedor.
     *
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false&aplicaPara=DE').subscribe(
            result => {
                this.ofes                    = result.data.ofes;
                this.paises                  = result.data.paises;
                this.tipoDocumentos          = result.data.tipo_documentos;
                this.tipoOrganizaciones      = result.data.tipo_organizaciones;
                this.tipoRegimen             = result.data.tipo_regimen;
                this.tiemposAceptacionTacita = result.data.tiempo_aceptacion_tacita;
                this.tiemposAceptacionTacita.map(el => {
                    el.tat_codigo_descripcion = el.tat_codigo + ' - ' + el.tat_descripcion;
                });
                this.ResFiscal = result.data.responsabilidades_fiscales;
                this.responsabilidadesFiscales = result.data.responsabilidades_fiscales;
                if (this._pro_identificacion) {
                    this.loadProveedor();
                } else {
                    this.loading(false);
                    this.tipoDocumentoSelect             = result.data.tipo_documentos;
                    this.tipoOrganizacionSelect          = result.data.tipo_organizaciones;
                    this.regimenFiscalSelect             = result.data.tipo_regimen;
                    this.responsabilidadesFiscalesSelect = result.data.responsabilidades_fiscales;
                }
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los parámetros', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Crea o actualiza un nuevo registro.
     *
     * @param values
     */
    public resourceProveedor(values) {
        if (this.formulario.valid) {
            const payload = this.getPayload();
            this.loading(true);
            if (this._pro_identificacion) {
                payload['estado'] = this.estado.value;
                this._configuracionService.updateProveedor(payload, this._pro_identificacion, this.ofe_id.value).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Proveedor actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/proveedores`, this._router);
                    },
                    error => {
                        this.loading(false);
                        const texto_errores = this.parseError(error);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el Proveedor', 'Ok', 'btn btn-danger');
                    });
            } else {
                this._configuracionService.create(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Proveedor creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/proveedores`, this._router);
                    },
                    error => {
                        this.loading(false);
                        const texto_errores = this.parseError(error);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el Proveedor', 'Ok', 'btn btn-danger');
                    });
            }
        }
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     */
    getPayload() {
        // console.log(this.usuariosRecepcion.controls['pro_usuarios_recepcion'].value);
        /* let usuariosRecepcion = [];
        if(this.selectedUsuarios) {
            this.selectedUsuarios.forEach(usuario => {
                let documento = usuario.split(' - ');
                usuariosRecepcion.push(documento[0]);
            });
        } */
        const payload = {
            ofe_identificacion: this.ofe_id.value,
            pro_identificacion: this.pro_identificacion.value,
            pro_id_personalizado: this.pro_id_personalizado.value,
            pro_razon_social: this.pro_razon_social.value,
            pro_nombre_comercial: this.pro_nombre_comercial.value,
            pro_primer_apellido: this.pro_primer_apellido.value,
            pro_segundo_apellido: this.pro_segundo_apellido.value,
            pro_primer_nombre: this.pro_primer_nombre.value,
            pro_otros_nombres: this.pro_otros_nombres.value,
            tdo_codigo: this.tdo_id.value,
            toj_codigo: this.toj_id.value,
            pai_codigo: this.pai_id.value && this.pai_id.value.pai_codigo ? this.pai_id.value.pai_codigo : null,
            dep_codigo: this.dep_id.value && this.dep_id.value.dep_codigo ? this.dep_id.value.dep_codigo : null,
            mun_codigo: this.mun_id.value && this.mun_id.value.mun_codigo ? this.mun_id.value.mun_codigo : null,
            cpo_codigo: this.cpo_id.value && this.cpo_id.value.cpo_codigo ? this.cpo_id.value.cpo_codigo : null,
            pro_direccion: this.pro_direccion.value,
            pai_codigo_domicilio_fiscal: this.pai_id_domicilio_fiscal.value && this.pai_id_domicilio_fiscal.value.pai_codigo ? this.pai_id_domicilio_fiscal.value.pai_codigo : null,
            dep_codigo_domicilio_fiscal: this.dep_id_domicilio_fiscal.value && this.dep_id_domicilio_fiscal.value.dep_codigo ? this.dep_id_domicilio_fiscal.value.dep_codigo : null,
            mun_codigo_domicilio_fiscal: this.mun_id_domicilio_fiscal.value && this.mun_id_domicilio_fiscal.value.mun_codigo ? this.mun_id_domicilio_fiscal.value.mun_codigo : null,
            cpo_codigo_domicilio_fiscal: this.cpo_id_domicilio_fiscal.value && this.cpo_id_domicilio_fiscal.value.cpo_codigo ? this.cpo_id_domicilio_fiscal.value.cpo_codigo : null,
            pro_direccion_domicilio_fiscal: this.pro_direccion_domicilio_fiscal.value,
            pro_telefono: this.pro_telefono.value,
            pro_correo: this.pro_correo.value,
            rfi_codigo: this.rfi_id.value,
            ref_codigo: this.ref_id.value,
            tat_codigo : this.tat_id.value && this.tat_id.value.tat_codigo ? this.tat_id.value.tat_codigo : null,
            pro_integracion_erp : this.pro_integracion_erp.value,
            pro_matricula_mercantil: this.pro_matricula_mercantil.value,
            pro_usuarios_recepcion: (this.selectedUsuarios !== undefined && this.selectedUsuarios !== null && this.selectedUsuarios.length > 0) ? JSON.stringify(this.selectedUsuarios) : null,
            pro_correos_notificacion : (this.pro_correos_notificacion && this.pro_correos_notificacion.value) ? this.pro_correos_notificacion.value.join(',') : '',
        };

        if (!this.tat_id || !this.tat_id.value) {
            delete payload.tat_codigo;
        }

        return payload;
    }
}
