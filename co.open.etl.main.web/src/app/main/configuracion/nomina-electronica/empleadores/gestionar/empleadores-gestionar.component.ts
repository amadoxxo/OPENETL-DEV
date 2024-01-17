import {Subject} from 'rxjs';
import {ActivatedRoute, Router} from '@angular/router';
import {BaseComponentView} from '../../../../core/base_component_view';
import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {AbstractControl, FormControl, FormBuilder, FormGroup, Validators} from '@angular/forms';

import {Auth} from '../../../../../services/auth/auth.service';
import {CommonsService} from '../../../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../../../services/configuracion/configuracion.service';

import {JwtHelperService} from '@auth0/angular-jwt';
import {MatAccordion} from '@angular/material/expansion';

@Component({
    selector: 'app-empleadores-gestionar',
    templateUrl: './empleadores-gestionar.component.html',
    styleUrls: ['./empleadores-gestionar.component.scss']
})
export class EmpleadoresGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion') acordion: MatAccordion;

    // Usuario en línea
    public usuario  : any;
    public objMagic = {};
    public ver      : boolean;
    public editar   : boolean;
    public titulo   : string;

    // Formulario y controles
    public emp_id               : AbstractControl;
    public tdo_codigo           : AbstractControl;
    public emp_identificacion   : AbstractControl;
    public tdo_digito           : AbstractControl;
    public emp_razon_social     : AbstractControl;
    public emp_primer_apellido  : AbstractControl;
    public emp_segundo_apellido : AbstractControl;
    public emp_primer_nombre    : AbstractControl;
    public emp_otros_nombres    : AbstractControl;
    public emp_web              : AbstractControl;
    public emp_twitter          : AbstractControl;
    public emp_facebook         : AbstractControl;
    public emp_correo           : AbstractControl;
    public sft_id               : AbstractControl;

    public pai_id                    : AbstractControl;
    public dep_id                    : AbstractControl;
    public mun_id                    : AbstractControl;
    public emp_direccion             : AbstractControl;
    public emp_telefono              : AbstractControl;
    public cpo_id                    : AbstractControl;
    public emp_prioridad_agendamiento: AbstractControl;
    public estado                    : AbstractControl;

    // Steppers
    public form                : FormGroup;
    public datosGenerales      : FormGroup;
    public domicilio           : FormGroup;
    public redesSociales       : FormGroup;
    public informacionAdicional: FormGroup;
    public softwareProveedor   : FormGroup;
    public configuracion: FormGroup;

    public tiposDocumento       : Array<any> = [];
    public arrTiposDoc          : Array<any> = [];
    public mostrarDV            : boolean = false;
    public mostrarCamposTdo31   : boolean = false;
    public codigoDocumentoNIT   = '31';
    public anchoFlex            = '49';
    public maxlengthIdentificacion: number = 20;
    public regexIdentificacion: RegExp = /^[0-9a-zA-Z-]{1,20}$/;
    public formErrors: any;

    public sptObjeto                   = {};
    public paisObjeto                  = {};
    public departamentoObjeto          = {};
    public municipioObjeto             = {};

    public recurso: string = 'Empleadores';
    public _emp_id: any;
    public _emp_identificacion: any;
    public aclsUsuario: any;

    private _unsubscribeAll: Subject<any> = new Subject();

    set setTiposDocumento(value:any) {
        let existe = false;
        this.arrTiposDoc.forEach(reg => {
            if (reg.tdo_codigo == value.tdo_codigo) {
                existe = true;
            }
        });

        if (!existe) {
            this.arrTiposDoc.push(value);
        }

        this.tiposDocumento = this.arrTiposDoc;
    }

    /**
     * Crea una instancia de EmpleadoresGestionarComponent.
     * 
     * @param {Router} _router
     * @param {ActivatedRoute} _route
     * @param {FormBuilder} _formBuilder
     * @param {CommonsService} _commonsService
     * @param {ConfiguracionService} _configuracionService
     * @param {JwtHelperService} jwtHelperService
     * @memberof EmpleadoresGestionarComponent
     */
    constructor(
        public _auth                 : Auth,
        private _router              : Router,
        private _route               : ActivatedRoute,
        private _formBuilder         : FormBuilder,
        private _commonsService      : CommonsService,
        private _configuracionService: ConfiguracionService,

        private jwtHelperService: JwtHelperService
    ) {
        super();
        this._configuracionService.setSlug = "nomina-electronica/empleadores";
        this.init();
        this.buildErrorsObject();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    /**
     * Vista construida.
     *
     * @memberof EmpleadoresGestionarComponent
     */
    ngAfterViewInit() {
        if (this.ver)
            this.acordion.openAll();
    }

    /**
     * Se inicializa el componente.
     *
     * @memberof EmpleadoresGestionarComponent
     */
    ngOnInit() {
        this._emp_id = this._route.snapshot.params['emp_id'];
        this._emp_identificacion = this._route.snapshot.params['emp_identificacion'];

        this.ver = false;
        if (this._router.url.indexOf('editar-empleador') !== -1) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
        } else if (this._router.url.indexOf('ver-empleador') !== -1) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true
        } else {
            this.titulo = 'Crear ' + this.recurso;
        }

        this.initForBuild();
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     *
     * @memberof EmpleadoresGestionarComponent
     */
    public buildErrorsObject() {
        this.formErrors = {
            emp_identificacion: {
                required: 'La Identificación es requerida!',
                maxLength: 'Ha introducido más de ' + this.maxlengthIdentificacion + ' caracteres'
            },
            tdo_codigo: {
                required: 'El Tipo de Documento es requerido!'
            },
            emp_razon_social: {
                required: 'La Razón Social es requerida!'
            },
            emp_primer_apellido: {
                required: 'El Primer Apellido es requerido!'
            },
            emp_primer_nombre: {
                required: 'El Primer Nombre es requerido!'
            },
            emp_correo: {
                email: 'El correo es inválido!'
            }
        };
    }

    /**
     * On destroy.
     *
     * @memberof EmpleadoresGestionarComponent
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
    }

    /**
     * Inicializacion de los diferentes controles.
     *
     * @memberof EmpleadoresGestionarComponent
     */
    init() {
        this.aclsUsuario = this._auth.getAcls();
        this.buildFormulario();
    }

    /**
     * Se encarga de cargar los datos de un empleador que se ha seleccionado en el tracking.
     *
     * @memberof EmpleadoresGestionarComponent
     */
    public loadEmpleador(): void {
        this.loading(true);
        this._configuracionService.get(this._emp_identificacion).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    let controlEstado: FormControl = new FormControl('', Validators.required);
                    this.form.addControl('estado', controlEstado);
                    this.estado = this.form.controls['estado'];
                    this.emp_identificacion.setValue(res.emp_identificacion);

                    if (res.get_tipo_documento) {
                        this.tdo_codigo.setValue(res.get_tipo_documento.tdo_codigo);
                        let tipoDocumento = res.get_tipo_documento;
                        tipoDocumento.tdo_codigo_descripion = res.get_tipo_documento.tdo_codigo + ' - ' + res.get_tipo_documento.tdo_descripcion;
                        this.setTiposDocumento = tipoDocumento;
                        this.cambiarTdo(tipoDocumento);
                    }

                    this.emp_razon_social.setValue(res.emp_razon_social);
                    this.emp_primer_apellido.setValue(res.emp_primer_apellido);
                    this.emp_segundo_apellido.setValue(res.emp_segundo_apellido);
                    this.emp_primer_nombre.setValue(res.emp_primer_nombre);
                    this.emp_otros_nombres.setValue(res.emp_otros_nombres);

                    this.emp_direccion.setValue(res.emp_direccion);
                    this.emp_telefono.setValue(res.emp_telefono);
                    this.emp_web.setValue(res.emp_web);
                    this.emp_correo.setValue(res.emp_correo);
                    this.emp_twitter.setValue(res.emp_twitter);
                    this.emp_facebook.setValue(res.emp_facebook);

                    if (res.get_proveedor_tecnologico) {
                        this.sptObjeto['sft_id'] = res.get_proveedor_tecnologico.sft_id;
                        this.sptObjeto['sft_identificador'] = res.get_proveedor_tecnologico.sft_identificador;
                        this.sptObjeto['sft_identificador_nombre'] = res.get_proveedor_tecnologico.sft_identificador + ' - ' + res.get_proveedor_tecnologico.sft_nombre;
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

                    this.pai_id.setValue(this.paisObjeto);
                    this.dep_id.setValue(this.departamentoObjeto);
                    this.mun_id.setValue(this.municipioObjeto);
                    this.sft_id.setValue(this.sptObjeto);

                    this.emp_prioridad_agendamiento.setValue((res.emp_prioridad_agendamiento !== null && res.emp_prioridad_agendamiento !== undefined && res.emp_prioridad_agendamiento !== '') ? res.emp_prioridad_agendamiento : null);

                    if (res.estado === 'ACTIVO') {
                        this.estado.setValue('ACTIVO');
                    } else {
                        this.estado.setValue('INACTIVO');
                    }
                    this.objMagic['fecha_creacion'] = res.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.fecha_modificacion;
                    this.objMagic['estado'] = res.estado;
                }
            },
            error => {
                this.loading(false);
                let texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Empleador', 'Ok', 'btn btn-danger', 'configuracion/nomina-electronica/empleadores', this._router);
            }
        );
    }

    /**
     * Construcción del formulario principal.
     *
     * @memberof EmpleadoresGestionarComponent
     */
    buildFormulario() {
        this.form = this._formBuilder.group({
            DatosGenerales      : this.buildFormularioDatosGenerales(),
            Domicilio           : this.buildFormularioDomicilio(),
            RedesSociales       : this.buildFormularioRedesSociales(),
            InformacionAdicional: this.buildFormularioInformacionAdicional(),
            DatosSft            : this.buildFormularioDatosSft(),
            configuracion: this.buildServiciosContratados(),
        });
    }

    /**
     * Construcción de los datos del selector de datos generales.
     *
     * @return {*} 
     * @memberof EmpleadoresGestionarComponent
     */
    buildFormularioDatosGenerales() {
        this.datosGenerales = this._formBuilder.group({
            tdo_codigo: this.requerido(),
            emp_identificacion: this.requerido(),
            tdo_digito: [''],
            emp_razon_social: [''],
            emp_primer_apellido: this.requerido(),
            emp_segundo_apellido: [''],
            emp_primer_nombre: this.requerido(),
            emp_otros_nombres: [''],
        });

        this.tdo_codigo           = this.datosGenerales.controls['tdo_codigo'];
        this.emp_identificacion   = this.datosGenerales.controls['emp_identificacion'];
        this.tdo_digito           = this.datosGenerales.controls['tdo_digito'];
        this.emp_razon_social     = this.datosGenerales.controls['emp_razon_social'];

        this.emp_primer_apellido  = this.datosGenerales.controls['emp_primer_apellido'];
        this.emp_segundo_apellido = this.datosGenerales.controls['emp_segundo_apellido'];
        this.emp_primer_nombre    = this.datosGenerales.controls['emp_primer_nombre'];
        this.emp_otros_nombres    = this.datosGenerales.controls['emp_otros_nombres'];

        return this.datosGenerales;
    }

    /**
     * Construcción del formulario de domicilio.
     *
     * @return {*} 
     * @memberof EmpleadoresGestionarComponent
     */
    buildFormularioDomicilio() {
        this.domicilio = this._formBuilder.group({
            pai_id: this.requerido(),
            dep_id: this.requerido(),
            mun_id: this.requerido(),
            emp_direccion: ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(255)
                ],
            )],
            emp_telefono: this.maxlong(50),
            cpo_id: this.maxlong(50)
        });

        this.pai_id = this.domicilio.controls['pai_id'];
        this.dep_id = this.domicilio.controls['dep_id'];
        this.mun_id = this.domicilio.controls['mun_id'];
        this.emp_direccion = this.domicilio.controls['emp_direccion'];
        this.emp_telefono = this.domicilio.controls['emp_telefono'];
        this.cpo_id = this.domicilio.controls['cpo_id'];

        return this.domicilio;
    }

    /**
     * Construcción del formulario de redes sociales.
     *
     * @return {*} 
     * @memberof EmpleadoresGestionarComponent
     */
    buildFormularioRedesSociales() {
        this.redesSociales = this._formBuilder.group({
            emp_web: this.maxlong(255),
            emp_twitter: this.maxlong(255),
            emp_facebook: this.maxlong(255)
        });

        this.emp_web      = this.redesSociales.controls['emp_web'];
        this.emp_twitter  = this.redesSociales.controls['emp_twitter'];
        this.emp_facebook = this.redesSociales.controls['emp_facebook'];

        return this.redesSociales;
    }

    /**
     * Construcción del formulario información adicional.
     *
     * @return {*} 
     * @memberof EmpleadoresGestionarComponent
     */
    buildFormularioInformacionAdicional() {
        this.informacionAdicional = this._formBuilder.group({
            emp_correo: ['', Validators.compose(
                [
                    Validators.email,
                    Validators.maxLength(255)
                ],
            )]
        });

        this.emp_correo   = this.informacionAdicional.controls['emp_correo'];

        return this.informacionAdicional;
    }

    /**
     * Construcción de los datos del proveedor de software tecnológico.
     *
     * @return {*} 
     * @memberof EmpleadoresGestionarComponent
     */
    buildFormularioDatosSft() {
        this.softwareProveedor = this._formBuilder.group({
            sft_id: this.requerido(),
        });
        this.sft_id = this.softwareProveedor.controls['sft_id'];
        return this.softwareProveedor;
    }

    /**
     * Construcción del formgroup para la selección de servicios contratados.
     *
     */
    buildServiciosContratados() {
        this.configuracion = this._formBuilder.group({
            emp_prioridad_agendamiento   : ['', [Validators.pattern(new RegExp(/^[0-9]{1,2}$/))]]
        });

        this.emp_prioridad_agendamiento    = this.configuracion.controls['emp_prioridad_agendamiento'];

        return this.configuracion;
    }

    /**
     * Permite regresar a la lista de empleadores.
     *
     * @memberof EmpleadoresGestionarComponent
     */
    regresar() {
        this._router.navigate(['configuracion/nomina-electronica/empleadores']);
    }

    /**
     * Inicializa la data necesaria para la construcción del empleador.
     *
     * @private
     * @memberof EmpleadoresGestionarComponent
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false&aplicaPara=DN').subscribe(
            result => {
                if (this._emp_identificacion) {
                    this.arrTiposDoc = result.data.tipo_documentos;
                    this.loadEmpleador();
                } else {
                    this.loading(false);
                    this.tiposDocumento = result.data.tipo_documentos;
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
     * @param {*} values Valores a guardar
     * @memberof EmpleadoresGestionarComponent
     */
    public resourceEmpleador(values) {
        let payload = this.getPayload();

        this.loading(true);
        let that = this;
        
        if (this.form.valid) {
            if (this._emp_identificacion) {
                payload['estado'] =  this.estado.value;
                this._configuracionService.update(payload, this._emp_identificacion).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Empleador actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/nomina-electronica/empleadores`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el Empleador', 'Ok', 'btn btn-danger');
                    }
                );
            } else {
                this._configuracionService.create(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Empleador creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/nomina-electronica/empleadores`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el Empleador', 'Ok', 'btn btn-danger');
                    }
                );
            }
        }
    }

    /**
     * Activa o Inactiva el campo del dígito de verificación.
     *
     * @param {*} evt Información del tipo de documento
     * @memberof EmpleadoresGestionarComponent
     */
    cambiarTdo(evt) {
        if (evt && evt.tdo_codigo && (evt.tdo_codigo === this.codigoDocumentoNIT)){
            this.mostrarDV = true;
            this.calcularDV();
            this.maxlengthIdentificacion = 20;
            this.regexIdentificacion = new RegExp("^[0-9]{1,20}$");
            this.mostrarCamposTdo31 = true;
            this.emp_razon_social.setValidators([Validators.required]);

            this.emp_primer_apellido.clearValidators();
            this.emp_primer_apellido.setValue(null);
            this.emp_segundo_apellido.clearValidators();
            this.emp_segundo_apellido.setValue(null);
            this.emp_primer_nombre.clearValidators();
            this.emp_primer_nombre.setValue(null);
            this.emp_otros_nombres.clearValidators();
            this.emp_otros_nombres.setValue(null);
        } else {
            this.mostrarDV = false; 
            this.tdo_digito.setValue(null);
            this.maxlengthIdentificacion = 20;
            this.regexIdentificacion = new RegExp("^[0-9a-zA-Z-]{1,20}$");
            this.mostrarCamposTdo31 = false;
            this.emp_razon_social.clearValidators();
            this.emp_razon_social.setValue(null);

            this.emp_primer_apellido.setValidators([Validators.required]);
            this.emp_primer_nombre.setValidators([Validators.required]);
        }
    }

    /**
     * Calcula el Dígito de Verificación para los NITs.
     *
     * @memberof EmpleadoresGestionarComponent
     */
    calcularDV() {
        if (this.mostrarDV) {
            if (this.emp_identificacion.value.trim() !== '') {
                this.loading(true);
                this._commonsService.calcularDV(this.emp_identificacion.value).subscribe(
                result => {
                    if (result.data || result.data === 0) {
                        this.tdo_digito.setValue(result.data);
                    }
                    this.loading(false);    
                }, error => {
                    const texto_errores = this.parseError(error);
                    this.loading(false);
                    this.showError(texto_errores, 'error', 'Error al calcular el DV', 'Ok', 'btn btn-danger');
                }
                );
            } else {
                this.tdo_digito.setValue(null);
            }
        }
    }

    /**
     * Permite realizar la busqueda de un tipo de documento.
     *
     * @param {string} term Texto de busqueda
     * @param {*} item Información del documento.
     * @return {*} 
     * @memberof EmpleadoresGestionarComponent
     */
    customSearchFnTdo(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.tdo_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.tdo_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     * @return {*} 
     * @memberof EmpleadoresGestionarComponent
     */
    getPayload(){
        let payload = {
            "tdo_codigo"                : this.tdo_codigo.value,
            "emp_identificacion"        : this.emp_identificacion.value,
            "emp_razon_social"          : this.emp_razon_social.value,
            "emp_primer_apellido"       : this.emp_primer_apellido.value,
            "emp_segundo_apellido"      : this.emp_segundo_apellido.value,
            "emp_primer_nombre"         : this.emp_primer_nombre.value,
            "emp_otros_nombres"         : this.emp_otros_nombres.value,
            "pai_codigo"                : this.pai_id.value && this.pai_id.value.pai_codigo ? this.pai_id.value.pai_codigo : '',
            "dep_codigo"                : this.dep_id.value && this.dep_id.value.dep_codigo ? this.dep_id.value.dep_codigo : '',
            "mun_codigo"                : this.mun_id.value && this.mun_id.value.mun_codigo ? this.mun_id.value.mun_codigo : '',
            "emp_direccion"             : this.emp_direccion.value,
            "emp_telefono"              : this.emp_telefono.value,
            "emp_web"                   : this.emp_web.value,
            "emp_correo"                : this.emp_correo.value,
            "emp_twitter"               : this.emp_twitter.value,
            "emp_facebook"              : this.emp_facebook.value,
            "sft_identificador"         : (this.sft_id.value && this.sft_id.value.sft_identificador) ? this.sft_id.value.sft_identificador : '',
            "sft_id"                    : (this.sft_id.value && this.sft_id.value.sft_id) ? this.sft_id.value.sft_id : '',
            "emp_prioridad_agendamiento": this.emp_prioridad_agendamiento.value
        };

        return payload;
    }
}
