import {Component, OnDestroy, OnInit, ViewChild, AfterViewInit} from '@angular/core';
import {BaseComponentView} from '../../../core/base_component_view';
import {ActivatedRoute, Router} from '@angular/router';
import {AbstractControl, FormArray, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {Subject} from 'rxjs';
import {CommonsService} from '../../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {RadianService} from '../../../../services/radian/radian.service';
import {MatAccordion} from '@angular/material/expansion';
import {JwtHelperService} from '@auth0/angular-jwt';
import {Auth} from '../../../../services/auth/auth.service';
import {DatosGeneralesRegistroComponent} from '../../../commons/datos-generales-registro/datos-generales-registro.component';
import {UbicacionOpenComponent} from './../../../commons/ubicacion-open/ubicacion-open.component';
import swal from 'sweetalert2';

@Component({
    selector: 'app-radian-actores-gestionar',
    templateUrl: './radian-actores-gestionar.component.html',
    styleUrls: ['./radian-actores-gestionar.component.scss']
})
export class RadianActoresGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion', {static: false}) acordion: MatAccordion;
    @ViewChild('DatosGenerales', {static: true}) datosGeneralesControl: DatosGeneralesRegistroComponent;
    @ViewChild('domicilioCorrespondencia', {static: false}) domicilioCorrespondencia: UbicacionOpenComponent;

    // Usuario en línea
    public usuario                        : any;
    public objMagic                       = {};
    public ver                            : boolean = false;
    public editar                         : boolean = false;
    // Formulario y controles
    public gestor                         : RadianActoresGestionarComponent;
    public formulario                     : FormGroup;
    public tdo_id                         : AbstractControl;
    public tat_id                         : AbstractControl;
    public toj_id                         : AbstractControl;
    public pai_id                         : AbstractControl;
    public mun_id                         : AbstractControl;
    public dep_id                         : AbstractControl;
    public DV                             : AbstractControl;
    public act_identificacion             : AbstractControl;
    public act_razon_social               : AbstractControl;
    public act_nombre_comercial           : AbstractControl;
    public act_primer_apellido            : AbstractControl;
    public act_segundo_apellido           : AbstractControl;
    public act_primer_nombre              : AbstractControl;
    public act_otros_nombres              : AbstractControl;
    public act_direccion                  : AbstractControl;
    public act_telefono                   : AbstractControl;
    public act_correo                     : AbstractControl;
    public cpo_id                         : AbstractControl;
    public act_correos_notificacion       : AbstractControl;
    public act_notificacion_un_solo_correo: AbstractControl;
    public sft_id                         : AbstractControl;
    public estado                         : AbstractControl;
    public titulo                         : string;

    public initOrganizacion = null;
    public initDV           = null;

    public sptObjeto          = {};
    public paisObjeto         = {};
    public departamentoObjeto = {};
    public municipioObjeto    = {};
    public codigoPostalObjeto = {};
    public tatObjeto          = {};
    public codigoNIT          = '31';

    public aclsUsuario: any;

    recurso = 'Radian Actores';

    _act_id: any;
    _act_identificacion: any;
    public paises                  : Array<any> = [];
    public arrRolesRadian          : Array<any> = [];
    public dataRolesRadian         : Array<any> = [];
    public cantRolesRadian         : number = 0;
    public tipoDocumentos          : Array<any> = [];
    public tipoOrganizaciones      : Array<any> = [];
    public tiemposAceptacionTacita : Array<any> = [];
    public tipoDocumentoSelect     : any = {};
    public tipoOrganizacionSelect  : any = {};

    datosGenerales        : FormGroup;
    ubicacion             : FormGroup;
    reglaAceptacionTacita : FormGroup;
    informacionAdicional  : FormGroup;
    correosNotificacion   : FormGroup;
    rolesRadian           : FormGroup;
    sftSelector           : FormGroup;

    roles                  : FormArray;
    public rol_id          : AbstractControl;
    public rol_descripcion : AbstractControl;

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
     * @param _radianService
     * @param iconRegistry
     * @param jwtHelperService
     */
    constructor(
        public _auth                  : Auth,
        private _router               : Router,
        private _route                : ActivatedRoute,
        private _formBuilder          : FormBuilder,
        private _commonsService       : CommonsService,
        private _configuracionService : ConfiguracionService,
        private _radianService        : RadianService,
        private jwtHelperService      : JwtHelperService
    ) {
        super();
        this._configuracionService.setSlug = 'radian/actor';
        this.gestor = this;
        this.init();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    ngOnInit() {
        this._act_id            = this._route.snapshot.params['act_id'];
        this._act_identificacion = this._route.snapshot.params['act_identificacion'];
        this.ver = false;
        this.initForBuild();
        if (this._act_identificacion && !this._act_id) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
        } else if (this._act_identificacion && this._act_id) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true;
        } else {
            this.titulo = 'Crear ' + this.recurso;
        }
    }

    /**
     * Inicializa la data necesaria para la construcción del actores.
     *
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=true&aplicaPara=DE').subscribe(
            result => {
                this.paises             = result.data.paises;
                this.arrRolesRadian     = result.data.roles_radian;
                this.tipoDocumentos     = result.data.tipo_documentos;
                this.tipoOrganizaciones = result.data.tipo_organizaciones;

                this.tiemposAceptacionTacita = result.data.tiempo_aceptacion_tacita;
                this.tiemposAceptacionTacita.map( el => {
                    el.tat_codigo_descripcion = el.tat_codigo + ' - ' + el.tat_descripcion;
                });

                if (!this.editar && !this.ver) {
                    this.dataRolesRadian = this.arrRolesRadian;
                    if (this.arrRolesRadian.length > 0) {
                        let roles_radian = this.formulario.get('rolesRadian.roles') as FormArray;
            
                        this.dataRolesRadian.forEach((item, index) => {
                            if (index > 0) {
                                this.agregarRolesRadian();
                            }
                            roles_radian.at(index).patchValue({
                                rol_id: false,
                                rol_descripcion: item.rol_id
                            });
                        });
                    }
                }

                if (this._act_identificacion) {
                    this.loadActor();
                } else {
                    this.loading(false);
                    this.tipoDocumentoSelect    = result.data.tipo_documentos;
                    this.tipoOrganizacionSelect = result.data.tipo_organizaciones;
                }
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los parámetros', 'Ok', 'btn btn-danger');
            }
        );
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
        this._unsubscribeAll.next(true);
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
     * Se encarga de cargar los datos de un actor que se ha seleccionado en el tracking.
     *
     */
    public loadActor(): void {
        this.loading(true);
        this._configuracionService.get(this._act_identificacion).subscribe(
            res => {
                if (res) {
                    if (res.data)
                        res = res.data;

                    if (res.get_parametro_tipo_documento) {
                        this.tdo_id.setValue(res.get_parametro_tipo_documento.tdo_codigo);
                        this.tipoDocumentoSelect = res.get_parametro_tipo_documento;
                    }

                    if (res.get_tipo_organizacion_juridica) {
                        this.toj_id.setValue(res.get_tipo_organizacion_juridica.toj_codigo);
                        this.tipoOrganizacionSelect = res.get_tipo_organizacion_juridica;
                    }

                    this.act_identificacion.setValue(res.act_identificacion);
                    this.act_razon_social.setValue((res.act_razon_social !== null) ? res.act_razon_social : '');
                    this.act_nombre_comercial.setValue((res.act_nombre_comercial !== null) ? res.act_nombre_comercial : '');
                    this.act_primer_apellido.setValue((res.act_primer_apellido !== null) ? res.act_primer_apellido : '');
                    this.act_segundo_apellido.setValue((res.act_segundo_apellido !== null) ? res.act_segundo_apellido : '');
                    this.act_primer_nombre.setValue((res.act_primer_nombre !== null) ? res.act_primer_nombre : '');
                    this.act_otros_nombres.setValue((res.act_otros_nombres !== null) ? res.act_otros_nombres : '');

                    this.act_direccion.setValue((res.act_direccion !== null) ? res.act_direccion : '');

                    this.act_telefono.setValue((res.act_telefono !== null) ? res.act_telefono : '');
                    this.act_correo.setValue(res.act_correo);
                    
                    if (res.get_tipo_organizacion_juridica)
                        this.initOrganizacion = res.get_tipo_organizacion_juridica.toj_codigo;

                    this.act_correos_notificacion.setValue(res.act_correos_notificacion ? res.act_correos_notificacion.split(',') : []);
                    this.act_notificacion_un_solo_correo.setValue((res.act_notificacion_un_solo_correo !== null && res.act_notificacion_un_solo_correo !== undefined && res.act_notificacion_un_solo_correo !== '') ? res.act_notificacion_un_solo_correo : 'NO');

                    if (res.get_configuracion_software_proveedor_tecnologico) {
                        this.sptObjeto['sft_id'] = res.get_configuracion_software_proveedor_tecnologico.sft_id;
                        this.sptObjeto['sft_identificador'] = res.get_configuracion_software_proveedor_tecnologico.sft_identificador;
                        this.sptObjeto['sft_identificador_nombre'] = res.get_configuracion_software_proveedor_tecnologico.sft_identificador + ' - ' + res.get_configuracion_software_proveedor_tecnologico.sft_nombre;
                    }
                    if (res.get_parametros_pais) {
                        this.paisObjeto['pai_id'] = res.get_parametros_pais.pai_id;
                        this.paisObjeto['pai_codigo'] = res.get_parametros_pais.pai_codigo;
                        this.paisObjeto['pai_codigo_descripion'] = res.get_parametros_pais.pai_codigo + ' - ' + res.get_parametros_pais.pai_descripcion;
                    }

                    if (res.get_parametros_departamento) {
                        this.departamentoObjeto['dep_id'] = res.get_parametros_departamento.dep_id;
                        this.departamentoObjeto['dep_codigo'] = res.get_parametros_departamento.dep_codigo;
                        this.departamentoObjeto['dep_codigo_descripion'] = res.get_parametros_departamento.dep_codigo + ' - ' + res.get_parametros_departamento.dep_descripcion;
                    }

                    if (res.get_parametros_municipio) {
                        this.municipioObjeto['mun_id'] = res.get_parametros_municipio.mun_id;
                        this.municipioObjeto['mun_codigo'] = res.get_parametros_municipio.mun_codigo;
                        this.municipioObjeto['mun_codigo_descripion'] = res.get_parametros_municipio.mun_codigo + ' - ' + res.get_parametros_municipio.mun_descripcion;
                    }

                    if (res.get_codigo_postal) {
                        this.codigoPostalObjeto['cpo_id'] = res.get_codigo_postal.cpo_id;
                        this.codigoPostalObjeto['cpo_codigo'] = res.get_codigo_postal.cpo_codigo;
                    }

                    if (res.get_tiempo_aceptacion_tacita){
                        this.tatObjeto['tat_id'] = res.get_tiempo_aceptacion_tacita.tat_id;
                        this.tatObjeto['tat_codigo'] = res.get_tiempo_aceptacion_tacita.tat_codigo;
                        this.tatObjeto['tat_codigo_descripcion'] = res.get_tiempo_aceptacion_tacita.tat_codigo + ' - ' + res.get_tiempo_aceptacion_tacita.tat_descripcion;
                    }

                    if (res.get_parametro_tipo_documento && res.get_parametro_tipo_documento.tdo_codigo === this.codigoNIT) {
                        if (res.act_identificacion) {
                            this._commonsService.calcularDV(res.act_identificacion).subscribe(
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

                    let roles_radian = this.formulario.get('rolesRadian.roles') as FormArray;
                    if (res.act_roles) {
                        this.dataRolesRadian = res.act_roles;
                        let arrRoles = res.act_roles;
                        arrRoles.forEach((item, index) => {
                            if (index > 0) {
                                this.agregarRolesRadian();
                            }

                            roles_radian.at(index).patchValue({
                                rol_id: true,
                                rol_descripcion: item.rol_id
                            });
                        });
                    }

                    this.pai_id.setValue(this.paisObjeto);
                    this.dep_id.setValue(this.departamentoObjeto);
                    this.mun_id.setValue(this.municipioObjeto);

                    if(Object.keys(this.sptObjeto).length > 0)
                        this.sft_id.setValue(this.sptObjeto);
                        
                    this.tat_id.setValue(this.tatObjeto);
                    this.cpo_id.setValue(this.codigoPostalObjeto);
                    this.estado.setValue(res.estado);
                    this.objMagic['fecha_creacion'] = res.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.fecha_modificacion;
                    this.objMagic['estado'] = res.estado;
                }
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el ACTOR', 'Ok', 'btn btn-danger', 'configuracion/radian-actores', this._router);
            }
        );
    }

    llenarCamposGroup(form_group, campos) {
        const formGroup = (this.formulario.controls['DatosContactos'].get(form_group) as FormGroup);
        const arrFormulario = formGroup.value;
        for (const x in arrFormulario) {
            arrFormulario[x] = campos[x];
            formGroup.controls[x.trim()].setValue(campos[x]);
        }
    }

    /**
     * Construccion del formulario principal.
     *
     */
    buildFormulario() {
        this.formulario = this._formBuilder.group({
            DatosGenerales               : this.getFormularioDatosGenerales(),
            Ubicacion                    : this.buildFormularioUbicacion(),
            InformacionAdicional         : this.buildFormularioInformacionAdicional(),
            DatosSft                     : this.buildFormularioDatosSft(),
            ReglaAceptacionTacita        : this.buildFormularioDatosTat(),
            CorreosNotificacion           : this.buildFormularioNotificaciones(),
            rolesRadian                  : this.buildFormularioRolesRadian()
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
            act_identificacion: this.requeridoMaxlong(20),
            act_razon_social: this.maxlong(255),
            act_nombre_comercial: this.maxlong(255),
            act_primer_apellido: this.maxlong(100),
            act_segundo_apellido: [''],
            act_primer_nombre: this.maxlong(100),
            act_otros_nombres: [''],
            DV: [''],
            estado: ['']
        });
        this.tdo_id = this.datosGenerales.controls['tdo_id'];
        this.toj_id = this.datosGenerales.controls['toj_id'];
        this.act_identificacion = this.datosGenerales.controls['act_identificacion'];
        this.act_razon_social = this.datosGenerales.controls['act_razon_social'];
        this.act_nombre_comercial = this.datosGenerales.controls['act_nombre_comercial'];
        this.act_primer_apellido = this.datosGenerales.controls['act_primer_apellido'];
        this.act_segundo_apellido = this.datosGenerales.controls['act_segundo_apellido'];
        this.act_primer_nombre = this.datosGenerales.controls['act_primer_nombre'];
        this.act_otros_nombres = this.datosGenerales.controls['act_otros_nombres'];
        this.DV = this.datosGenerales.controls['DV'];
        this.estado = this.datosGenerales.controls['estado'];
        return this.datosGenerales;
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
            act_direccion: this.maxlong(255),
            cpo_id: this.maxlong(50)
        });
        this.pai_id = this.ubicacion.controls['pai_id'];
        this.dep_id = this.ubicacion.controls['dep_id'];
        this.mun_id = this.ubicacion.controls['mun_id'];
        this.act_direccion = this.ubicacion.controls['act_direccion'];
        this.cpo_id = this.ubicacion.controls['cpo_id'];
        return this.ubicacion;
    }

    /**
     * Construcción del formulario de información adicional.
     *
     */
    buildFormularioInformacionAdicional() {
        this.informacionAdicional = this._formBuilder.group({
            act_correo: ['', Validators.compose(
                [
                    Validators.required,
                    Validators.email,
                    Validators.maxLength(255),
                    Validators.minLength(10)
                ],
            )],
            act_telefono : this.maxlong(255)
        });
        this.act_telefono = this.informacionAdicional.controls['act_telefono'];
        this.act_correo   = this.informacionAdicional.controls['act_correo'];

        return this.informacionAdicional;
    }

    /**
     * Construccion del formgroup para el campo de notificaciones.
     *
     */
    buildFormularioNotificaciones() {
        this.correosNotificacion = this._formBuilder.group({
            act_correos_notificacion: [''],
            act_notificacion_un_solo_correo: ['']
        });
        this.act_correos_notificacion = this.correosNotificacion.controls['act_correos_notificacion'];
        this.act_notificacion_un_solo_correo = this.correosNotificacion.controls['act_notificacion_un_solo_correo'];
        return this.correosNotificacion;
    }

    /**
     * Construcción del formulario de roles radian.
     *
     */
    buildFormularioRolesRadian() {
        this.rolesRadian = this._formBuilder.group({
            roles: this.buildFormularioRolesRadianArray()
        });
        return this.rolesRadian;
    }

    /**
     * Construcción del array de roles radian.
     *
     */
    buildFormularioRolesRadianArray() {
        this.roles = this._formBuilder.array([
            this.createRolesRadian()
        ]);
        return this.roles;
    }

    /**
     * Permite la creación dinámica de las diferentes filas y campos en el apartado Roles Radian.
     *
     */
    private createRolesRadian(): any {
        return this._formBuilder.group({
            rol_id: [''],
            rol_descripcion: ['']
        });
    }

    /**
     * Crea un nuevo item de Roles Radian.
     *
     */
    agregarRolesRadian() {
        const CTRL = this.formulario.get('rolesRadian.roles') as FormArray;
        CTRL.push(this.createRolesRadian());
    }

    /**
     * Construcción de los datos del proveedor de software tecnológico.
     *
     */
    buildFormularioDatosSft() {
        this.sftSelector = this._formBuilder.group({
            sft_id   : [''],
        });
        this.sft_id    = this.sftSelector.controls['sft_id'];
        return this.sftSelector;
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
     * Permite regresar a la lista de actores.
     *
     */
    regresar() {
        this._router.navigate(['configuracion/radian-actores']);
    }

    /**
     * Crea o actualiza un nuevo registro.
     *
     * @param values
     */
    public resourceActor(values) {
        if (this.formulario.valid) {
            if(!this.sft_id.value)
                this.showError('<h4>Debe indicar el Software Proveedor Tecnológico para Emisión o Documento Soporte</h4>', 'error', 'Error al procesar el ACTOR', 'Ok', 'btn btn-danger');
            else {
                this.loading(true);
                const payload = this.getPayload();
                if (this._act_identificacion) {
                    payload['estado'] = this.estado.value;
                    this._radianService.update(this._act_identificacion, payload).subscribe(
                        response => {
                            this.loading(false);
                            this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'ACTOR actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/radian-actores`, this._router);
                        },
                        error => {
                            this.loading(false);
                            const texto_errores = this.parseError(error);
                            this.showError('<h4 style="text-align:left">' + texto_errores + '</h4>', 'error', 'Error al actualizar el ACTOR', 'Ok', 'btn btn-danger');
                        });
                } else {
                    this._radianService.create(payload).subscribe(
                        response => {
                            this.loading(false);
                            this.showSuccess('<h3>' + '</h3>', 'success', 'ACTOR creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/radian-actores`, this._router);
                        },
                        error => {
                            this.loading(false);
                            const texto_errores = this.parseError(error);
                            this.showError('<h4 style="text-align:left">' + texto_errores + '</h4>', 'error', 'Error al guardar el ACTOR', 'Ok', 'btn btn-danger');
                        });
                }
            }
        }
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     */
    getPayload() {
        let direccionesAdicionales = [];
        let ctrlDireccionesAdicionales = this.domicilioCorrespondencia.formDireccionesAdicionales.get('direcciones_adicionales') as FormArray
        ctrlDireccionesAdicionales.controls.forEach(ctrlDireccion => {
            direccionesAdicionales.push(ctrlDireccion.value.direccion)
        });

        let rolesRadian = [];
        if(this.formulario.get('rolesRadian.roles').value){
            this.formulario.get('rolesRadian.roles').value.forEach((element) => {
                if (element.rol_id == true) {
                    rolesRadian.push(element.rol_descripcion);
                }
            });
        }

        const payload = {
            sft_id: this.sft_id.value && this.sft_id.value.sft_id ? this.sft_id.value.sft_id : '',
            sft_identificador: this.sft_id.value && this.sft_id.value.sft_identificador ? this.sft_id.value.sft_identificador : '',
            act_identificacion: this.act_identificacion.value,
            act_razon_social: this.act_razon_social.value,
            act_nombre_comercial: this.act_nombre_comercial.value,
            act_primer_apellido: this.act_primer_apellido.value,
            act_segundo_apellido: this.act_segundo_apellido.value,
            act_primer_nombre: this.act_primer_nombre.value,
            act_otros_nombres: this.act_otros_nombres.value,
            act_roles: JSON.stringify(rolesRadian),
            tdo_codigo: this.tdo_id.value,
            toj_codigo: this.toj_id.value,
            pai_codigo: this.pai_id.value && this.pai_id.value.pai_codigo ? this.pai_id.value.pai_codigo : '',
            dep_codigo: this.dep_id.value && this.dep_id.value.dep_codigo ? this.dep_id.value.dep_codigo : '',
            mun_codigo: this.mun_id.value && this.mun_id.value.mun_codigo ? this.mun_id.value.mun_codigo : '',
            cpo_codigo: this.cpo_id.value && this.cpo_id.value.cpo_codigo ? this.cpo_id.value.cpo_codigo : '',
            act_direccion: this.act_direccion.value ? this.act_direccion.value : '',
            act_correos_notificacion: this.act_correos_notificacion && this.act_correos_notificacion.value ? this.act_correos_notificacion.value.join(',') : '',
            act_notificacion_un_solo_correo : this.act_notificacion_un_solo_correo.value,
            act_telefono: this.act_telefono.value,
            act_correo: this.act_correo.value,
            tat_codigo : this.tat_id.value && this.tat_id.value.tat_codigo ? this.tat_id.value.tat_codigo : ''
        };

        return payload;
    }

    /**
     * Gestiona el cambio de selección en los radio buttons de notificación.
     *
     */
    changeCorreosNotificacion(value) {
        if (value === 'SI') {
            this.act_correos_notificacion.setValidators([Validators.required]);
        } else {
            this.act_correos_notificacion.clearValidators();
            this.act_correos_notificacion.setValue(null);
        }
        this.act_correos_notificacion.updateValueAndValidity();
    }
}
