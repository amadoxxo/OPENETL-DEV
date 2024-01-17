import {Subject} from 'rxjs';
import {ActivatedRoute, Router} from '@angular/router';
import {BaseComponentView} from '../../../../core/base_component_view';
import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {AbstractControl, FormControl, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {debounceTime, distinctUntilChanged, filter, finalize, switchMap, tap} from 'rxjs/operators';

import {CommonsService} from '../../../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../../../services/configuracion/configuracion.service';

import * as moment from 'moment';
import {JwtHelperService} from '@auth0/angular-jwt';
import { MatAccordion } from '@angular/material/expansion';

@Component({
    selector: 'app-trabajadores-gestionar',
    templateUrl: './trabajadores-gestionar.component.html',
    styleUrls: ['./trabajadores-gestionar.component.scss']
})
export class TrabajadoresGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion') acordion: MatAccordion;

    // Usuario en línea
    public usuario: any;
    public objMagic = {};
    public ver: boolean;
    public editar: boolean;
    public titulo: string;

    filteredEmpleadores: any = [];
    isLoading = false;
    public noCoincidences: boolean;

    // Formulario y controles
    public tra_id               : AbstractControl;
    public tdo_codigo           : AbstractControl;
    public tra_identificacion   : AbstractControl;
    public tra_razon_social     : AbstractControl;
    public tra_primer_apellido  : AbstractControl;
    public tra_segundo_apellido : AbstractControl;
    public tra_primer_nombre    : AbstractControl;
    public tra_otros_nombres    : AbstractControl;

    public emp_id               : AbstractControl;
    public emp_identificacion   : AbstractControl;

    public pai_id               : AbstractControl;
    public dep_id               : AbstractControl;
    public mun_id               : AbstractControl;
    public tra_direccion        : AbstractControl;
    public tra_telefono         : AbstractControl;
    public cpo_id               : AbstractControl;

    public tra_codigo           : AbstractControl;
    public tra_fecha_ingreso    : AbstractControl;
    public tra_fecha_retiro     : AbstractControl;
    public tra_sueldo           : AbstractControl;
    public ntc_codigo           : AbstractControl;
    public ntt_codigo           : AbstractControl;
    public nst_codigo           : AbstractControl;
    public tra_alto_riesgo      : AbstractControl;
    public tra_salario_integral : AbstractControl;

    public tra_tipo_cuenta      : AbstractControl;
    public tra_entidad_bancaria : AbstractControl;
    public tra_numero_cuenta    : AbstractControl;

    // Steppers
    public form                 : FormGroup;
    public datosGenerales       : FormGroup;
    public datosEmpleador       : FormGroup;
    public domicilio            : FormGroup;
    public informacionTrabajador: FormGroup;
    public informacionBancaria  : FormGroup;
  
    public estado               : AbstractControl;
    public tiposDocumento       : Array<any> = [];
    public arrTiposDoc          : Array<any> = [];
    public codigoDocumentoNIT   = '31';
    public maxlengthIdentificacion: number = 20;
    public regexIdentificacion: RegExp = /^[0-9a-zA-Z-]{1,20}$/;
    public formErrors: any;

    public tiposTrabajador       : Array<any> = [];
    public arrTiposTrabajador    : Array<any> = [];
    public subtiposTrabajador    : Array<any> = [];
    public arrSubtiposTrabajador : Array<any> = [];
    public tiposContrato         : Array<any> = [];
    public arrTiposContrato      : Array<any> = [];

    public sptObjeto              = {};
    public paisObjeto             = {};
    public departamentoObjeto     = {};
    public municipioObjeto        = {};

    recurso: string = 'Trabajadores';
    _tra_id: any;
    _tra_identificacion: any;
    _emp_identificacion: any;

    // Private
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

        this.tiposDocumento = this.arrTiposDoc.filter(reg => reg.tdo_codigo !== '31');
    }

    set setTiposContrato(value:any) {
        let existe = false;
        this.arrTiposContrato.forEach(reg => {
            if (reg.ntc_codigo == value.ntc_codigo) {
                existe = true;
            }
        });

        if (!existe) {
            this.arrTiposContrato.push(value);
        }

        this.tiposContrato = this.arrTiposContrato;
    }

    set setTiposTrabajdor(value:any) {
        let existe = false;
        this.arrTiposTrabajador.forEach(reg => {
            if (reg.ntt_codigo == value.ntt_codigo) {
                existe = true;
            }
        });

        if (!existe) {
            this.arrTiposTrabajador.push(value);
        }

        this.tiposTrabajador = this.arrTiposTrabajador;
    }

    set setSubtiposTrabajdor(value:any) {
        let existe = false;
        this.arrSubtiposTrabajador.forEach(reg => {
            if (reg.nst_codigo == value.nst_codigo) {
                existe = true;
            }
        });

        if (!existe) {
            this.arrSubtiposTrabajador.push(value);
        }

        this.subtiposTrabajador = this.arrSubtiposTrabajador;
    }

    /**
     * Crea una instancia de TrabajadoresGestionarComponent.
     * 
     * @param {Router} _router
     * @param {ActivatedRoute} _route
     * @param {FormBuilder} _formBuilder
     * @param {CommonsService} _commonsService
     * @param {ConfiguracionService} _configuracionService
     * @param {JwtHelperService} jwtHelperService
     * @memberof TrabajadoresGestionarComponent
     */
    constructor(
        private _router: Router,
        private _route: ActivatedRoute,
        private _formBuilder: FormBuilder,
        private _commonsService: CommonsService,
        private _configuracionService: ConfiguracionService,

        private jwtHelperService: JwtHelperService
    ) {
        super();
        this._configuracionService.setSlug = "nomina-electronica/trabajadores";
        this.init();
        this.buildErrorsObject();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    /**
     * Vista construida.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    ngAfterViewInit() {
        if (this.ver)
            this.acordion.openAll();
    }

    /**
     * Se inicializa el componente.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    ngOnInit() {
        this._tra_id = this._route.snapshot.params['tra_id'];
        this._tra_identificacion = this._route.snapshot.params['tra_identificacion'];
        this._emp_identificacion = this._route.snapshot.params['emp_identificacion'];

        this.ver = false;
        if (this._router.url.indexOf('editar-trabajador') !== -1) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
            this.valueChangesEmpleadores();
        } else if (this._router.url.indexOf('ver-trabajador') !== -1) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true
        } else {
            this.titulo = 'Crear ' + this.recurso;
            this.valueChangesEmpleadores();
        }

        this.initForBuild();
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    public buildErrorsObject() {
        this.formErrors = {
            tra_identificacion: {
                required: 'La Identificación es requerida!',
                maxLength: 'Ha introducido más de ' + this.maxlengthIdentificacion + ' caracteres'
            },
            tdo_codigo: {
                required: 'El Tipo de Documento es requerido!'
            },
            tra_razon_social: {
                required: 'La Razón Social es requerida!'
            },
            tra_primer_apellido: {
                required: 'El Primer Apellido es requerido!'
            },
            tra_primer_nombre: {
                required: 'El Primer Nombre es requerido!'
            },
            emp_identificacion: {
                required: 'El Empleador es requerido!'
            },
            ntt_codigo: {
                required: 'El Tipo de Trabajador es requerido!'
            },
            nst_codigo: {
                required: 'El Subtipo de Trabajador es requerido!'
            },
            ntc_codigo: {
                required: 'El Tipo de Contrato es requerido!'
            },
            tra_sueldo: {
                required: 'El Sueldo es requerido!'
            },
            tra_salario_integral: {
                required: 'El Salario Integral es requerido!'
            }
        };
    }

    /**
     * On destroy.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
    }

    /**
     * Inicializacion de los diferentes controles.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    init() {
        this.buildFormulario();
    }

    /**
     * Se encarga de cargar los datos de un trabajador que se ha seleccionado en el tracking.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    public loadTrabajador(): void {
        this.loading(true);
        this._configuracionService.getTrabajador(this._tra_identificacion, this._emp_identificacion).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    let controlEstado: FormControl = new FormControl('', Validators.required);
                    this.form.addControl('estado', controlEstado);
                    this.estado = this.form.controls['estado'];

                    if (res.data.get_tipo_documento) {
                        this.tdo_codigo.setValue(res.data.get_tipo_documento.tdo_codigo);
                        let tipoDocumento = res.data.get_tipo_documento;
                        tipoDocumento.tdo_codigo_descripion = res.data.get_tipo_documento.tdo_codigo + ' - ' + res.data.get_tipo_documento.tdo_descripcion;
                        this.setTiposDocumento = tipoDocumento;
                    }

                    if (res.data.get_empleador) {
                        let nombre_empleador = '';
                        if (res.data.get_empleador.emp_razon_social != '' && res.data.get_empleador.emp_razon_social != null) {
                            nombre_empleador = res.data.get_empleador.emp_razon_social;
                        } else {
                            nombre_empleador  = res.data.get_empleador.emp_primer_nombre != null ? res.data.get_empleador.emp_primer_nombre + ' ' : ' ';
                            nombre_empleador += res.data.get_empleador.emp_otros_nombres != null ? res.data.get_empleador.emp_otros_nombres + ' ' : ' ';
                            nombre_empleador += res.data.get_empleador.emp_primer_apellido != null ? res.data.get_empleador.emp_primer_apellido + ' ' : ' ';
                            nombre_empleador += res.data.get_empleador.emp_segundo_apellido != null ? res.data.get_empleador.emp_segundo_apellido : '';
                        }


                        this.emp_id.setValue(res.data.get_empleador.emp_identificacion);
                        this.emp_identificacion.setValue(res.data.get_empleador.emp_identificacion + ' - ' + nombre_empleador);
                    }

                    this.tra_identificacion.setValue(res.data.tra_identificacion);
                    this.tra_razon_social.setValue(res.data.tra_razon_social);

                    this.tra_primer_apellido.setValue(res.data.tra_primer_apellido);
                    this.tra_segundo_apellido.setValue(res.data.tra_segundo_apellido);
                    this.tra_primer_nombre.setValue(res.data.tra_primer_nombre);
                    this.tra_otros_nombres.setValue(res.data.tra_otros_nombres);

                    this.tra_direccion.setValue(res.data.tra_direccion);
                    this.tra_telefono.setValue(res.data.tra_telefono);
                    
                    this.tra_codigo.setValue(res.data.tra_codigo);
                    this.tra_fecha_ingreso.setValue(res.data.tra_fecha_ingreso);
                    this.tra_fecha_retiro.setValue(res.data.tra_fecha_retiro);
                    this.tra_sueldo.setValue(res.data.tra_sueldo);

                    this.tra_alto_riesgo.setValue(res.data.tra_alto_riesgo);
                    this.tra_salario_integral.setValue(res.data.tra_salario_integral);
                    this.tra_entidad_bancaria.setValue(res.data.tra_entidad_bancaria);
                    this.tra_tipo_cuenta.setValue(res.data.tra_tipo_cuenta);
                    this.tra_numero_cuenta.setValue(res.data.tra_numero_cuenta);

                    if (res.data.get_parametros_tipo_contrato) {
                        this.ntc_codigo.setValue(res.data.get_parametros_tipo_contrato.ntc_codigo);
                        let tipoContrato = res.data.get_parametros_tipo_contrato;
                        tipoContrato.ntc_codigo_descripion = res.data.get_parametros_tipo_contrato.ntc_codigo + ' - ' + res.data.get_parametros_tipo_contrato.ntc_descripcion;
                        this.setTiposContrato = tipoContrato;
                    }

                    if (res.data.get_parametros_tipo_trabajador) {
                        this.ntt_codigo.setValue(res.data.get_parametros_tipo_trabajador.ntt_codigo);
                        let tipoTrabajador = res.data.get_parametros_tipo_trabajador;
                        tipoTrabajador.ntt_codigo_descripion = res.data.get_parametros_tipo_trabajador.ntt_codigo + ' - ' + res.data.get_parametros_tipo_trabajador.ntt_descripcion;
                        this.setTiposTrabajdor = tipoTrabajador;
                    }

                    if (res.data.get_parametros_subtipo_trabajador) {
                        this.nst_codigo.setValue(res.data.get_parametros_subtipo_trabajador.nst_codigo);
                        let subtipoTrabajador = res.data.get_parametros_subtipo_trabajador;
                        subtipoTrabajador.nst_codigo_descripion = res.data.get_parametros_subtipo_trabajador.nst_codigo + ' - ' + res.data.get_parametros_subtipo_trabajador.nst_descripcion;
                        this.setSubtiposTrabajdor = subtipoTrabajador;
                    }

                    if (res.data.get_parametros_pais) {
                        this.paisObjeto['pai_id'] = res.data.get_parametros_pais.pai_id;
                        this.paisObjeto['pai_codigo'] = res.data.get_parametros_pais.pai_codigo;
                        this.paisObjeto['pai_codigo_descripion'] = res.data.get_parametros_pais.pai_codigo + ' - ' + res.data.get_parametros_pais.pai_descripcion;
                    }
                    if (res.data.get_parametros_departamento) {
                        this.departamentoObjeto['dep_id'] = res.data.get_parametros_departamento.dep_id;
                        this.departamentoObjeto['dep_codigo'] = res.data.get_parametros_departamento.dep_codigo;
                        this.departamentoObjeto['dep_codigo_descripion'] = res.data.get_parametros_departamento.dep_codigo + ' - ' + res.data.get_parametros_departamento.dep_descripcion;
                    }
                    if (res.data.get_parametros_municipio) {
                        this.municipioObjeto['mun_id'] = res.data.get_parametros_municipio.mun_id;
                        this.municipioObjeto['mun_codigo'] = res.data.get_parametros_municipio.mun_codigo;
                        this.municipioObjeto['mun_codigo_descripion'] = res.data.get_parametros_municipio.mun_codigo + ' - ' + res.data.get_parametros_municipio.mun_descripcion;
                    }

                    this.pai_id.setValue(this.paisObjeto);
                    this.dep_id.setValue(this.departamentoObjeto);
                    this.mun_id.setValue(this.municipioObjeto);

                    if (res.data.estado === 'ACTIVO') {
                        this.estado.setValue('ACTIVO');
                    } else {
                        this.estado.setValue('INACTIVO');
                    }
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;
                }
            },
            error => {
                this.loading(false);
                let texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Trabajador', 'Ok', 'btn btn-danger', 'configuracion/nomina-electronica/trabajadores', this._router);
            }
        );
    }

    /**
     * Construcción del formulario principal.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    buildFormulario() {
        this.form = this._formBuilder.group({
            DatosGenerales       : this.buildFormularioDatosGenerales(),
            DatosEmpleador       : this.buildFormularioDatosEmpleador(),
            Domicilio            : this.buildFormularioDomicilio(),
            InformacionTrabajador: this.buildFormularioInformacionTrabajador(),
            InformacionBancaria  : this.buildFormularioInformacionBancaria(),
        });
    }

    /**
     * Construcción de los datos del selector de datos generales.
     *
     * @return {*} 
     * @memberof TrabajadoresGestionarComponent
     */
    buildFormularioDatosGenerales() {
        this.datosGenerales = this._formBuilder.group({
            tdo_codigo: this.requerido(),
            tra_identificacion: this.requerido(),
            tra_razon_social: [''],
            tra_primer_apellido: this.requerido(),
            tra_segundo_apellido: [''],
            tra_primer_nombre: this.requerido(),
            tra_otros_nombres: [''],
        });

        this.tdo_codigo           = this.datosGenerales.controls['tdo_codigo'];
        this.tra_identificacion   = this.datosGenerales.controls['tra_identificacion'];
        this.tra_razon_social     = this.datosGenerales.controls['tra_razon_social'];
        this.tra_primer_apellido  = this.datosGenerales.controls['tra_primer_apellido'];
        this.tra_segundo_apellido = this.datosGenerales.controls['tra_segundo_apellido'];
        this.tra_primer_nombre    = this.datosGenerales.controls['tra_primer_nombre'];
        this.tra_otros_nombres    = this.datosGenerales.controls['tra_otros_nombres'];

        return this.datosGenerales;
    }

    /**
     * Construcción del formulario de domicilio.
     *
     * @return {*} 
     * @memberof TrabajadoresGestionarComponent
     */
    buildFormularioDomicilio() {
        this.domicilio = this._formBuilder.group({
            pai_id: [''],
            dep_id: [''],
            mun_id: [''],
            tra_direccion: this.maxlong(255),
            tra_telefono: this.maxlong(50),
            cpo_id: this.maxlong(50)
        });

        this.pai_id = this.domicilio.controls['pai_id'];
        this.dep_id = this.domicilio.controls['dep_id'];
        this.mun_id = this.domicilio.controls['mun_id'];
        this.tra_direccion = this.domicilio.controls['tra_direccion'];
        this.tra_telefono = this.domicilio.controls['tra_telefono'];
        this.cpo_id = this.domicilio.controls['cpo_id'];

        return this.domicilio;
    }

    /**
     * Construcción del formulario de información trabajador.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    buildFormularioInformacionTrabajador() {
        this.informacionTrabajador = this._formBuilder.group({
            tra_codigo: [''],
            tra_fecha_ingreso: this.requerido(),
            tra_fecha_retiro: [''],
            tra_sueldo: this.requerido(),
            tra_alto_riesgo: [''],
            tra_salario_integral: this.requerido(),
            ntc_codigo: this.requerido(),
            ntt_codigo: this.requerido(),
            nst_codigo: this.requerido()
        });

        this.tra_codigo        = this.informacionTrabajador.controls['tra_codigo'];
        this.tra_fecha_ingreso = this.informacionTrabajador.controls['tra_fecha_ingreso'];
        this.tra_fecha_retiro  = this.informacionTrabajador.controls['tra_fecha_retiro'];
        this.tra_sueldo        = this.informacionTrabajador.controls['tra_sueldo'];
        this.ntc_codigo        = this.informacionTrabajador.controls['ntc_codigo'];
        this.ntt_codigo        = this.informacionTrabajador.controls['ntt_codigo'];
        this.nst_codigo        = this.informacionTrabajador.controls['nst_codigo'];
        this.tra_alto_riesgo   = this.informacionTrabajador.controls['tra_alto_riesgo'];
        this.tra_salario_integral = this.informacionTrabajador.controls['tra_salario_integral'];

        return this.informacionTrabajador;
    }

    /**
     * Construcción del formulario de información bancaria.
     *
     * @memberof TrabajadoresGestionarComponent
     */
     buildFormularioInformacionBancaria() {
        this.informacionBancaria = this._formBuilder.group({
            tra_tipo_cuenta: [''],
            tra_entidad_bancaria: [''],
            tra_numero_cuenta: ['']
        });

        this.tra_tipo_cuenta      = this.informacionBancaria.controls['tra_tipo_cuenta'];
        this.tra_entidad_bancaria = this.informacionBancaria.controls['tra_entidad_bancaria'];
        this.tra_numero_cuenta    = this.informacionBancaria.controls['tra_numero_cuenta'];

        return this.informacionBancaria;
    }

    /**
     * Construcción del formulario del empleador.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    buildFormularioDatosEmpleador() {
        this.datosEmpleador = this._formBuilder.group({
            emp_id: this.requerido(),
            emp_identificacion: this.requerido()
        });

        this.emp_id = this.datosEmpleador.controls['emp_id'];
        this.emp_identificacion = this.datosEmpleador.controls['emp_identificacion'];

        return this.datosEmpleador;
    }

    /**
     * Realiza una búsqueda de los empleadores que pertenecen a la base de datos del usuario autenticacado.
     * 
     * Muestra una lista de empleadores según la coincidencia del valor diligenciado en el input text de emp_identificacion.
     * La lista se muestra con la siguiente estructura: Identificación - Nombre,
     * 
     * @memberof TrabajadoresGestionarComponent
     */
    valueChangesEmpleadores(): void {
        this.datosEmpleador
        .get('emp_identificacion')
        .valueChanges
        .pipe(
            filter(value => value.length >= 1),
            debounceTime(1000),
            distinctUntilChanged(),
            tap(() => {
                this.loading(true);
                this.datosEmpleador.get('emp_identificacion').disable();
            }),
            switchMap(value =>
                this._configuracionService.searchEmpleadores(value)
                    .pipe(
                        finalize(() => {
                            this.loading(false);
                            this.datosEmpleador.get('emp_identificacion').enable();
                        })
                    )
            )
        )
        .subscribe(res => {
            this.filteredEmpleadores = res.data;
            if (this.filteredEmpleadores.length <= 0) {
                this.filteredEmpleadores = [];
                this.noCoincidences = true;
            } else {
                this.noCoincidences = false;
            }    
        });
    }

    /**
     * Asigna los valores del empleador seleccionado en el autocompletar.
     *
     * @param {*} empleador Información el registro
     * @memberof TrabajadoresGestionarComponent
     */
    setEmpId(empleador: any): void {
        this.emp_id.setValue(empleador.emp_identificacion, {emitEvent: false});   
        this.emp_identificacion.setValue(empleador.emp_identificacion + ' - ' + empleador.nombre_completo, {emitEvent: false});
    }

    /**
     * Limpia la lista de los empleadores obtenidos en el autocompletar del campo emp_identificacion.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    clearEmpleador(): void {
        if (this.emp_identificacion.value === ''){
            this.filteredEmpleadores = [];
        }
    }

    /**
     * Permite regresar a la lista de trabajadores.
     *
     * @memberof TrabajadoresGestionarComponent
     */
    regresar() {
        this._router.navigate(['configuracion/nomina-electronica/trabajadores']);
    }

    /**
     * Inicializa la data necesaria para la construcción del trabajador.
     *
     * @private
     * @memberof TrabajadoresGestionarComponent
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false&aplicaPara=DN').subscribe(
            result => {
                if (this._tra_identificacion) {
                    this.arrTiposDoc = result.data.tipo_documentos;
                    this.arrTiposTrabajador = result.data.tipo_trabajador;
                    this.arrSubtiposTrabajador = result.data.subtipo_trabajador;
                    this.arrTiposContrato = result.data.tipo_contratos;

                    this.loadTrabajador();
                } else {
                    this.loading(false);
                    this.tiposDocumento = result.data.tipo_documentos.filter(reg => reg.tdo_codigo !== '31');;
                    this.tiposTrabajador = result.data.tipo_trabajador;
                    this.subtiposTrabajador = result.data.subtipo_trabajador;
                    this.tiposContrato = result.data.tipo_contratos;
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
     * @memberof TrabajadoresGestionarComponent
     */
    public resourceTrabajador(values) {
        let payload = this.getPayload();

        this.loading(true);
        let that = this;
        
        if (this.form.valid) {
            if (this._tra_identificacion) {
                payload['estado'] =  this.estado.value;
                this._configuracionService.updateTrabajador(payload, this._tra_identificacion, this._emp_identificacion).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Trabajador actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/nomina-electronica/trabajadores`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el Trabajador', 'Ok', 'btn btn-danger');
                    }
                );
            } else {
                this._configuracionService.create(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Trabajador creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/nomina-electronica/trabajadores`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el Trabajador', 'Ok', 'btn btn-danger');
                    }
                );
            }
        }
    }

    /**
     * Activa o Inactiva el campo del dígito de verificación.
     *
     * @param {*} evt Información del tipo de documento
     * @memberof TrabajadoresGestionarComponent
     */
    cambiarTdo(evt) {
        if (evt && evt.tdo_codigo && (evt.tdo_codigo === this.codigoDocumentoNIT)){
            this.maxlengthIdentificacion = 20;
            this.regexIdentificacion = new RegExp("^[0-9]{1,20}$");
            this.tra_razon_social.setValidators([Validators.required]);
        } else {
            this.maxlengthIdentificacion = 20;
            this.regexIdentificacion = new RegExp("^[0-9a-zA-Z-]{1,20}$");
            this.tra_razon_social.clearValidators();
        }
    }

    /**
     * Permite realizar la búsqueda de un tipo de documento.
     *
     * @param {string} term Texto de búsqueda
     * @param {*} item Información del documento.
     * @return {*} 
     * @memberof TrabajadoresGestionarComponent
     */
    customSearchFnTdo(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.tdo_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.tdo_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Permite realizar la búsqueda de un tipo de trabajador.
     *
     * @param {string} term Texto de búsqueda
     * @param {*} item Información del trabajador.
     * @return {*} 
     * @memberof TrabajadoresGestionarComponent
     */
    customSearchFnNtt(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.ntt_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.ntt_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Permite realizar la búsqueda de un subtipo de trabajador.
     *
     * @param {string} term Texto de búsqueda
     * @param {*} item Información del subtipo trabajador.
     * @return {*} 
     * @memberof TrabajadoresGestionarComponent
     */
    customSearchFnNst(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.nst_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.nst_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Permite realizar la búsqueda de un tipo de contrato.
     *
     * @param {string} term Texto de búsqueda
     * @param {*} item Información del tipo de contrato.
     * @return {*} 
     * @memberof TrabajadoresGestionarComponent
     */
     customSearchFnNtc(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.ntc_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.ntc_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     * @return {*} 
     * @memberof TrabajadoresGestionarComponent
     */
    getPayload(){
        let payload = {
            "emp_identificacion"    : this.emp_id.value,
            "tdo_codigo"            : this.tdo_codigo.value,
            "tra_identificacion"    : this.tra_identificacion.value,
            "tra_razon_social"      : this.tra_razon_social.value,
            "tra_primer_apellido"   : this.tra_primer_apellido.value,
            "tra_segundo_apellido"  : this.tra_segundo_apellido.value,
            "tra_primer_nombre"     : this.tra_primer_nombre.value,
            "tra_otros_nombres"     : this.tra_otros_nombres.value,
            "pai_codigo"            : this.pai_id.value && this.pai_id.value.pai_codigo ? this.pai_id.value.pai_codigo : '',
            "dep_codigo"            : this.dep_id.value && this.dep_id.value.dep_codigo ? this.dep_id.value.dep_codigo : '',
            "mun_codigo"            : this.mun_id.value && this.mun_id.value.mun_codigo ? this.mun_id.value.mun_codigo : '',
            "tra_direccion"         : this.tra_direccion.value,
            "tra_telefono"          : this.tra_telefono.value,
            "tra_codigo"            : this.tra_codigo.value,
            "tra_fecha_ingreso"     : (this.tra_fecha_ingreso.value != '' && this.tra_fecha_ingreso.value != null) ? moment(this.tra_fecha_ingreso.value).format('YYYY-MM-DD') : '',
            "tra_fecha_retiro"      : (this.tra_fecha_retiro.value != '' && this.tra_fecha_retiro.value != null) ? moment(this.tra_fecha_retiro.value).format('YYYY-MM-DD') : '',
            "tra_sueldo"            : this.tra_sueldo.value,
            "ntc_codigo"            : this.ntc_codigo.value,
            "ntt_codigo"            : this.ntt_codigo.value,
            "nst_codigo"            : this.nst_codigo.value,
            "tra_alto_riesgo"       : this.tra_alto_riesgo.value,
            "tra_salario_integral"  : this.tra_salario_integral.value,
            "tra_entidad_bancaria"  : this.tra_entidad_bancaria.value,
            'tra_tipo_cuenta'       : this.tra_tipo_cuenta.value,
            'tra_numero_cuenta'     : this.tra_numero_cuenta.value,
        };

        return payload;
    }
}
