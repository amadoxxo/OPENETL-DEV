import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {BaseComponentView} from '../../../core/base_component_view';
import {ActivatedRoute, Router} from '@angular/router';
import {AbstractControl, FormControl, FormBuilder, FormGroup, FormArray, Validators} from '@angular/forms';
import {Subject} from 'rxjs';
import {debounceTime, distinctUntilChanged, filter, finalize, switchMap, tap} from 'rxjs/operators';
import * as moment from 'moment';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';

import {JwtHelperService} from '@auth0/angular-jwt';
import {MatAccordion} from '@angular/material/expansion';

@Component({
    selector: 'app-usuarios-ecm-gestionar',
    templateUrl: './usuarios-ecm-gestionar.component.html',
    styleUrls: ['./usuarios-ecm-gestionar.component.scss']
})
export class UsuariosEcmGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion') acordion: MatAccordion;

    // Usuario en línea
    public usuario: any;
    public objMagic = {};
    public ver: boolean;
    public editar: boolean;
    public titulo: string;

    filteredUsuarios: any = [];
    isLoading = false;
    public noCoincidences: boolean;

    // Formulario y controles
    public usu_id               : AbstractControl;
    public usu_identificacion   : AbstractControl;
    public usu_nombre           : AbstractControl;
    public usu_email            : AbstractControl;

    // Steppers
    public form               : FormGroup;
    public datosGenerales     : FormGroup; 
    public infoIntegracionEcm : FormGroup;
    public informacionOfes    : FormArray;

    public usu_identificacion_nombre : string; 
    public estado                    : AbstractControl;
    public ofes                      : Array<any> = [];
    public arrRolesEcm               : Array<any> = [];

    public formErrors: any;

    recurso: string = 'Usuario openECM';
    _use_id: any;
    _use_identificador: any;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    /**
     * Crea una instancia de UsuariosEcmGestionarComponent.
     * 
     * @param {Router} _router
     * @param {ActivatedRoute} _route
     * @param {FormBuilder} _formBuilder
     * @param {ConfiguracionService} _configuracionService
     * @param {JwtHelperService} jwtHelperService
     * @memberof UsuariosEcmGestionarComponent
     */
    constructor(
        private _router: Router,
        private _route: ActivatedRoute,
        private _formBuilder: FormBuilder,
        private _configuracionService: ConfiguracionService,
        private jwtHelperService: JwtHelperService
    ) {
        super();
        this._configuracionService.setSlug = "usuarios-ecm";
        this.init();
        this.buildErrorsObject();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    /**
     * Vista construida.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    ngAfterViewInit() {
        if (this.ver)
            this.acordion.openAll();
    }

    /**
     * Se inicializa el componente.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    ngOnInit() {
        this._use_id = this._route.snapshot.params['use_id'];
        this._use_identificador = this._route.snapshot.params['use_identificador'];

        this.ver = false;
        if (this._router.url.indexOf('editar-usuario-ecm') !== -1) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
            this.usu_identificacion.disable();
            this.valueChangesUsuarios();
        } else if (this._router.url.indexOf('ver-usuario-ecm') !== -1) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true
            this.usu_identificacion.disable();
        } else {
            this.titulo = 'Crear ' + this.recurso;
            this.valueChangesUsuarios();
        }

        if (this._use_identificador) {
            this.loadUsuarioEcm();
        } 
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    public buildErrorsObject() {
        this.formErrors = {
            usu_identificacion: {
                required: 'La Identificación del Usuario es requerida!',
            },
            ros_id: {
                required: 'El Rol del Usuario es requerido!',
            }
        };
    }

    /**
     * On destroy.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
    }

    /**
     * Inicializacion de los diferentes controles.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    init() {
        this.buildFormulario();
    }

    /**
     * Se encarga de cargar los datos de un usuario de openECM que se ha seleccionado en el tracking.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    public loadUsuarioEcm(): void {
        this.loading(true);
        this._configuracionService.get(this._use_identificador).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    let informacion_ecm_form = (<FormArray>this.form.get('infoIntegracionEcm.informacionOfes'));
                    let controlEstado: FormControl = new FormControl('', Validators.required);
                    this.form.addControl('estado', controlEstado);
                    this.estado = this.form.controls['estado'];

                    this.setUsuId(res.data.usuario);

                    res.data.informacion_ecm.forEach((item, index) => {
                        if (index > 0) {
                            this.agregarInfoIntegracionEcm();
                        }

                        let roles = [];
                        item.roles.forEach(reg => {
                            const objRol = new Object();
                            objRol['ros_id'] = reg.ros_id;
                            objRol['ros_descripcion'] = reg.ros_descripcion;
                            objRol['ros_id_descripcion'] = reg.ros_id + ' - ' + reg.ros_descripcion;
                            roles.push(objRol);
                        });
                        this.arrRolesEcm[index] = roles;

                        informacion_ecm_form.at(index).patchValue({
                            ofe_id: item.ofe_id,
                            ros_id: item.ros_id
                        });
                    });

                    if (res.data.estado === 'ACTIVO') {
                        this.estado.setValue('ACTIVO');
                    } else {
                        this.estado.setValue('INACTIVO');
                    }

                    this.objMagic['fecha_creacion'] = String(moment(res.data.fecha_creacion).format('YYYY-MM-DD HH:mm:ss'));
                    this.objMagic['fecha_modificacion'] = String(moment(res.data.fecha_modificacion).format('YYYY-MM-DD HH:mm:ss'));
                    this.objMagic['estado'] = res.data.estado;
                }
            },
            error => {
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Usuario openECM', 'Ok', 'btn btn-danger', 'configuracion/usuarios-ecm', this._router);
            }
        );
    }

    /**
     * Construcción del formulario principal.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    buildFormulario() {
        this.form = this._formBuilder.group({
            datosGenerales     : this.buildFormularioDatosGenerales(),
            infoIntegracionEcm : this.buildFormularioinfoIntegracionEcm()
        });
    }

    /**
     * Construcción de los datos del selector de datos generales.
     *
     * @return {*} 
     * @memberof UsuariosEcmGestionarComponent
     */
    buildFormularioDatosGenerales() {
        this.datosGenerales = this._formBuilder.group({
            usu_id: [''],
            usu_identificacion: this.requerido(),
            usu_nombre: [''],
            usu_email: ['']
        });

        this.usu_id             = this.datosGenerales.controls['usu_id'];
        this.usu_identificacion = this.datosGenerales.controls['usu_identificacion'];
        this.usu_nombre         = this.datosGenerales.controls['usu_nombre'];
        this.usu_email          = this.datosGenerales.controls['usu_email'];
        return this.datosGenerales;
    }

    /**
     * Construccion del formulario de medio de pago.
     *
     */
    buildFormularioinfoIntegracionEcm() {
        this.infoIntegracionEcm = this._formBuilder.group({
            informacionOfes: this._formBuilder.array([this.agregarCamposInfoIntegracionEcm()]),
        });

        return this.infoIntegracionEcm;
    }

    /**
     * Permite regresar a la lista de usuarios de openECmM.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    regresar() {
        this._router.navigate(['configuracion/usuarios-ecm']);
    }

    /**
     * Agrega un FormGroup de la integración a openECM al formulario.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    agregarInfoIntegracionEcm(): void {
        this.informacionOfes = this.infoIntegracionEcm.get('informacionOfes') as FormArray;
        this.informacionOfes.push(this.agregarCamposInfoIntegracionEcm());
    }

    /**
     * Contruye un FormGroup de la integración a openECM al formulario.
     *
     * @return {FormGroup} 
     * @memberof TabDatosGeneralesComponent
     */
    agregarCamposInfoIntegracionEcm(): FormGroup {
        return this._formBuilder.group({
            ofe_id: ['', Validators.compose(
                [
                    Validators.required
                ]
            )],
            ros_id: ['', Validators.compose(
                [
                    Validators.required
                ]
            )]
        });
    }

    /**
     * ELimina un ofe y rol de la grilla.
     *
     * @param {number} i Índice del control
     * @memberof TabDatosGeneralesComponent
     */
    eliminarInfoIntegracionEcm(i: number) {
        const CTRL = <FormArray>this.infoIntegracionEcm.controls['informacionOfes'];
        CTRL.removeAt(i);
    }

    /**
     * Obtiene los roles de openECM a partir de un ofe seleccionado.
     *
     * @param {*} ofe Identificación del OFE
     * @param {*} index Índice del control
     * 
     * @memberof UsuariosEcmGestionarComponent
     */
    onSelectOfe(ofe, index) {
        this.loading(true);
        this._configuracionService.obtenerRolesEcm(ofe).subscribe(
            res => {
                if (res) {
                    (this.form.get('infoIntegracionEcm.informacionOfes') as FormArray).at(index).get('ros_id').setValue('');

                    let roles = [];
                    res.data.forEach(reg => {
                        const objRol = new Object();
                        objRol['ros_id'] = reg.ros_id;
                        objRol['ros_descripcion'] = reg.ros_descripcion;
                        objRol['ros_id_descripcion'] = reg.ros_id + ' - ' + reg.ros_descripcion;
                        roles.push(objRol);
                    });

                    this.loading(false);
                    this.arrRolesEcm[index] = roles;
                }
            },
            error => {
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Roles de openECM', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Crea o actualiza un nuevo registro.
     *
     * @param {*} values Valores del formulario
     * @memberof UsuariosEcmGestionarComponent
     */
    public resourceUsuarioEcm(values) {
        let payload = this.getPayload(values);

        this.loading(true);
        let that = this;
        
        if (this.form.valid) {
            if (this._use_identificador) {
                payload['estado'] =  this.estado.value;
                this._configuracionService.update(payload, this._use_identificador).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Usuario openECM actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/usuarios-ecm`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el Usuario openECM', 'Ok', 'btn btn-danger');
                    }
                );
            } else {
                this._configuracionService.create(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Usuario openECM creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/usuarios-ecm`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el Usuario openECM', 'Ok', 'btn btn-danger');
                    }
                );
            }
        }
    }

    /**
     * Realiza una búsqueda de los usuarios que pertenecen a la base de datos del usuario autenticacado.
     * 
     * Muestra una lista de usuarios según la coincidencia del valor diligenciado en el input text de usu_identificacion.
     * La lista se muestra con la siguiente estructura: Identificación - Nombre,
     * 
     * @memberof UsuariosEcmGestionarComponent
     */
    valueChangesUsuarios(): void {
        this.datosGenerales
        .get('usu_identificacion')
        .valueChanges
        .pipe(
            filter(value => value.length >= 1),
            debounceTime(1000),
            distinctUntilChanged(),
            tap(() => {
                this.loading(true);
                this.datosGenerales.get('usu_identificacion').disable();
            }),
            switchMap(value =>
                this._configuracionService.searchUsuarios(value)
                    .pipe(
                        finalize(() => {
                            this.loading(false);
                            this.datosGenerales.get('usu_identificacion').enable();
                        })
                    )
            )
        )
        .subscribe(res => {
            this.filteredUsuarios = res.data;
            if (this.filteredUsuarios.length <= 0) {
                this.filteredUsuarios = [];
                this.noCoincidences = true;
            } else {
                this.noCoincidences = false;
            }    
        });
    }

    /**
     * Asigna los valores del usuario seleccionado en el autocompletar.
     *
     * @param {*} usuario Información el registro
     * @memberof UsuariosEcmGestionarComponent
     */
    setUsuId(usuario: any): void {
        this.usu_id.setValue(usuario.usu_id, {emitEvent: false});   
        this.usu_identificacion_nombre = usuario.usu_identificacion_nombre;
        this.usu_identificacion.setValue(usuario.usu_identificacion, {emitEvent: false});
        this.usu_nombre.setValue(usuario.usu_nombre, {emitEvent: false});
        this.usu_email.setValue(usuario.usu_email, {emitEvent: false});

        this.loading(true);
        this._configuracionService.consultaOfes(usuario.usu_identificacion).subscribe(
            result => {
                this.loading(false);
                this.ofes = [];
                result.data.forEach(ofe => {
                    ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                    this.ofes.push(ofe);
                });                
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los Ofes', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Limpia la lista de los usuarios obtenidos en el autocompletar del campo usu_identificacion.
     *
     * @memberof UsuariosEcmGestionarComponent
     */
    clearUsuario(): void {
        if (this.usu_identificacion.value === ''){
            this.filteredUsuarios = [];
        }
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     * @param {*} form form Valores del formulario
     * @return {object} payload 
     * @memberof UsuariosEcmGestionarComponent
     */
    getPayload(form) {
        let arrInformacionEcm = [];
        form.infoIntegracionEcm.informacionOfes.forEach(reg => {
            const objInformacionEcm = new Object();
            objInformacionEcm['ofe_identificacion'] = reg.ofe_id;
            objInformacionEcm['use_rol'] = reg.ros_id;
            arrInformacionEcm.push(objInformacionEcm);
        });

        let payload = {
            "usu_identificacion" : this.usu_identificacion.value,
            "informacion_ecm"    : arrInformacionEcm
        };
        return payload;
    }

    /**
     * Permite hacer una búsqueda del registro.
     *
     * @param {string} term Texto escrito por el usuario
     * @param {*} item Infomación del item
     * @return {*} 
     * @memberof UsuariosEcmGestionarComponent
     */
    customSearchFnRol(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.ros_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }
}
