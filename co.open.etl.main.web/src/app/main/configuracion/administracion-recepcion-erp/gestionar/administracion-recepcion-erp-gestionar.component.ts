import {Subject, Subscription} from 'rxjs';
import {ActivatedRoute, Router} from '@angular/router';
import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {AbstractControl, FormControl, FormBuilder, FormGroup, FormArray, Validators} from '@angular/forms';
import {debounceTime, distinctUntilChanged, filter, finalize, switchMap, tap} from 'rxjs/operators';
import {CommonsService} from '../../../../services/commons/commons.service';
import {BaseComponentView} from 'app/main/core/base_component_view';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {MatAccordion} from '@angular/material/expansion';
import {JwtHelperService} from '@auth0/angular-jwt';
@Component({
    selector: 'app-administracion-recepcion-erp-gestionar',
    templateUrl: './administracion-recepcion-erp-gestionar.component.html',
    styleUrls: ['./administracion-recepcion-erp-gestionar.component.scss']
})
export class AdministracionRecepcionErpGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion', {static: false}) acordion: MatAccordion;

    // Usuario en línea
    public usuario: any;
    public objMagic = {};
    public ver: boolean;
    public editar: boolean;
    public titulo: string;

    isLoading = false;
    public noCoincidences: boolean;

    // Formulario y controles
    public ofe_identificacion    : AbstractControl;
    public ate_erp               : AbstractControl;
    public ate_descripcion       : AbstractControl;
    public ate_aplica_para       : AbstractControl;
    public ate_id                : AbstractControl;
    public origen                : AbstractControl;
    public xde_id                : AbstractControl;
    public xde_descripcion       : AbstractControl;
    public ate_condicion         : AbstractControl;
    public ate_valor             : AbstractControl;
    public ate_deben_aplica      : AbstractControl;
    public ate_accion            : AbstractControl;
    public ate_accion_titulo     : AbstractControl;
    public accion_origen         : AbstractControl;
    public xde_accion_id         : AbstractControl;
    public xde_accion_descripcion: AbstractControl;

    // Steppers
    public form: FormGroup;

    // Variables propias del componente
    public condicionGlobal       : FormGroup;
    public condicionesGlobales   : FormArray;
    public mostrarCondicionGlobal: boolean = false;
    public resultadosAutocomplete: Array<any> = [];
    public descripcionCondiciones: String[] = [];

    public estado                : AbstractControl;
    public arrTiposDoc           : Array<any> = [];
    public ofes                  : Array<any> = [];
    public erp                   : Array<any> = [];
    public idsEliminados         : Array<any> = [];
    public itemsAplica           : Array<Object> = [
        { id: 'FC',             name: 'FC' },
        { id: 'NC',             name: 'NC' },
        { id: 'ND',             name: 'ND' }
    ];
    public itemsDebenAplicar     : Array<Object> = [
        { id: 'TODAS',          name: 'Todas' },
        { id: 'ALGUNA',         name: 'Alguna' }
    ];
    public itemsAccion           : Array<Object> = [
        { id: 'NO_TRANSMITIR',  name: 'No Transmitir' },
        { id: 'NOTIFICAR',      name: 'Incluir en el cuerpo del correo de notificación' },
        { id: 'EXCLUIR_CIERRE', name: 'Excluir documento de fecha de cierre' }
    ];
    public itemsCondicion        : Array<Object> = [
        { id: 'IGUAL',          name: 'Igual' },
        { id: 'NO_ES_IGUAL',    name: 'No es igual' },
        { id: 'MENOR',          name: 'Menor' },
        { id: 'MENOR_O_IGUAL',  name: 'Menor o Igual' },
        { id: 'MAYOR',          name: 'Mayor' },
        { id: 'MAYOR_O_IGUAL',  name: 'Mayor o Igual' },
        { id: 'CONTENGA',       name: 'Contenga' },
        { id: 'NO_CONTENGA',    name: 'No contenga' },
        { id: 'COMIENZA',       name: 'Comienza' },
        { id: 'NO_COMIENZA',    name: 'No comienza' },
        { id: 'TERMINA',        name: 'Termina' },
        { id: 'NO_TERMINA',     name: 'No termina' }
    ];
    
    public formErrors: any;

    datosGenerales: FormGroup;
    datosRegla    : FormGroup;
    datosAccion   : FormGroup;

    recurso: string = 'Administración Recepción ERP';
    _ate_id: any;
    _ate_grupo: any;
    _ofe_identificacion: any;

    // Private
    private _unsubscribeAll         : Subject<any> = new Subject();
    private subscriptionsCondiciones: Subscription[] = [];
    private subscriptionsAccion     : Subscription[] = [];

    /**
     * Crea una instancia de AdministracionRecepcionErpGestionarComponent.
     * 
     * @param {Router} _router
     * @param {ActivatedRoute} _route
     * @param {FormBuilder} _formBuilder
     * @param {CommonsService} _commonsService
     * @param {ConfiguracionService} _configuracionService
     * @param {JwtHelperService} jwtHelperService
     * @memberof AdministracionRecepcionErpGestionarComponent
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
        this._configuracionService.setSlug = "administracion-recepcion-erp";
        this.usuario = this.jwtHelperService.decodeToken();
    }

    /**
     * Vista construida.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    ngAfterViewInit() {
        if (this.ver) {
            this.acordion.openAll();
        }
    }

    /**
     * Se inicializa el componente.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    ngOnInit() {
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
        this.buildFormulario();
        this.buildErrorsObject();

        this._ate_id = this._route.snapshot.params['ate_id'];
        this._ate_grupo = this._route.snapshot.params['ate_grupo'];
        this._ofe_identificacion = this._route.snapshot.params['ofe_identificacion'];

        this.ver = false;
        if (this._router.url.indexOf('editar-administracion-recepcion-erp') !== -1) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
        } else if (this._router.url.indexOf('ver-administracion-recepcion-erp') !== -1) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true
        } else {
            this.titulo = 'Crear ' + this.recurso;
        }

        this.initForBuild();
    }

    /**
     * On destroy.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
        if (this.subscriptionsCondiciones && this.subscriptionsCondiciones.length > 0) {
            this.subscriptionsCondiciones.forEach(s => s.unsubscribe());
        }
        if (this.subscriptionsAccion && this.subscriptionsAccion.length > 0) {
            this.subscriptionsAccion.forEach(s => s.unsubscribe());
        }
    }

    /**
     * Agrega un FormGroup de condicion global al formulario.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    agregarCamposCondicionGlobal(): void {
        const CTRL = this.datosRegla.get('condicionesGlobales') as FormArray;
        const INDICE = CTRL.length;
        
        CTRL.push(
            this._formBuilder.group({
                ate_id          : [''],
                origen          : [''],
                xde_id          : [''],
                xde_descripcion : [''],
                ate_condicion   : [''],
                ate_valor       : ['']
            })
        );

        this.valueChangesCondicion(INDICE);
    }

    ofeAplicaHasChange(event){
        const CTRL = this.datosRegla.get('condicionesGlobales') as FormArray;
        const INDICE = CTRL.length;
        for (let index = INDICE; index !== 0; index--) {
            this.eliminarCondicionGlobal(index-1);
        }
        
        this.datosAccion.get('xde_accion_descripcion').setValue('');
    }

    /**
     * Agrega las validaciones de obligatoriedad de los campos.
     *
     * @param {number} indice Índice del control
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    agregarRequeridosCondicionGlobal(indice: number){
        const CTRL: any  = this.datosRegla.get('condicionesGlobales') as FormArray;
        let valueXdeDescripcion = CTRL.controls[indice].controls['xde_descripcion'].value;
        if(valueXdeDescripcion != undefined && valueXdeDescripcion != '') {
            CTRL.controls[indice].controls['origen'].setValidators([Validators.required]);
            CTRL.controls[indice].controls['xde_id'].setValidators([Validators.required]);
            CTRL.controls[indice].controls['xde_descripcion'].setValidators([Validators.required]);
            CTRL.controls[indice].controls['ate_condicion'].setValidators([Validators.required]);
            CTRL.controls[indice].controls['ate_valor'].setValidators([Validators.required]);
        } else {
            this.limpiarCampoCondicionGlobal(indice);
        }
        CTRL.controls[indice].controls['origen'].updateValueAndValidity({emitEvent: false});
        CTRL.controls[indice].controls['xde_id'].updateValueAndValidity({emitEvent: false});
        CTRL.controls[indice].controls['xde_descripcion'].updateValueAndValidity({emitEvent: false});
        CTRL.controls[indice].controls['ate_condicion'].updateValueAndValidity({emitEvent: false});
        CTRL.controls[indice].controls['ate_valor'].updateValueAndValidity({emitEvent: false});
    }

    /**
     * Limpia los valores y validators de los campos del item de Condiciones.
     *
     * @param {number} indice Índice del control
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    limpiarCampoCondicionGlobal(indice: number) {
        const CTRL: any  = this.datosRegla.get('condicionesGlobales') as FormArray;

        CTRL.controls[indice].controls['ate_id'].clearValidators();
        CTRL.controls[indice].controls['origen'].clearValidators();
        CTRL.controls[indice].controls['xde_id'].clearValidators();
        CTRL.controls[indice].controls['xde_descripcion'].clearValidators();
        CTRL.controls[indice].controls['ate_condicion'].clearValidators();
        CTRL.controls[indice].controls['ate_valor'].clearValidators();

        CTRL.controls[indice].controls['ate_id'].setValue('');
        CTRL.controls[indice].controls['origen'].setValue('');
        CTRL.controls[indice].controls['xde_id'].setValue('');
        CTRL.controls[indice].controls['xde_descripcion'].setValue('');
        CTRL.controls[indice].controls['ate_condicion'].setValue('');
        CTRL.controls[indice].controls['ate_valor'].setValue('');
    }

    /**
     * ELimina una condicion global de la grilla.
     *
     * @param {number} indice Índice del control
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    eliminarCondicionGlobal(indice: number, ate_id?:any) {
        this.idsEliminados.push(ate_id);
        const CTRL = <FormArray>this.datosRegla.controls['condicionesGlobales'];
        // Se elimina la subscripción de la posición y se reasignan las subscripciones
        if (this.subscriptionsCondiciones && this.subscriptionsCondiciones.length > 0) {
            this.subscriptionsCondiciones.forEach(s => s.unsubscribe());
        }
        if(CTRL.length > 1)
            CTRL.removeAt(indice);
        else {
            this.limpiarCampoCondicionGlobal(indice);
            this.agregarRequeridosCondicionGlobal(indice);
        }

        // Genera nuevamente las subscripciones de los autocompletes
        for (let i = 0; i < CTRL.length; i++) {
            this.valueChangesCondicion(i);
        }
    }

    /**
     * Evalua los cambios en el autoComplete de la sección condiciones.
     *
     * @param {number} indice Posición del FormArray
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    valueChangesCondicion(indice: number){
        const CTRL = this.datosRegla.get('condicionesGlobales') as FormArray;

        this.subscriptionsCondiciones.push(
            CTRL.controls[indice]
            .get('xde_descripcion')
            .valueChanges
            .pipe(
                filter(value => value.length >= 1),
                debounceTime(1000),
                distinctUntilChanged(),
                tap(() => {
                    this.loading(true);
                    this.resultadosAutocomplete['condiciones'] = [];
                    this.noCoincidences = true;
                    CTRL.controls[indice].get('xde_descripcion').disable();
                }),
                switchMap(value =>
                    this._configuracionService.obtenerCondiciones(value, this.ate_aplica_para.value, this.ofe_identificacion.value)
                        .pipe(
                            finalize(() => {
                                this.loading(false);
                                CTRL.controls[indice].get('xde_descripcion').enable();
                            })
                        )
                )
            )
            .subscribe(res => {
                this.resultadosAutocomplete['condiciones'] = res.data;
                if (this.resultadosAutocomplete['condiciones'].length <= 0) {
                    CTRL.controls[indice].get('origen').setValue('');
                    this.noCoincidences = true;
                } else {
                    this.noCoincidences = false;
                }
            })
        );
    }

    /**
     * Establece los valores para origen, id y descripcion cuando un item es seleccionado en un autocomplete.
     *
     * @param {object} registroSeleccionado
     * @memberof ProductosGestionarComponent
     */
    setValorCondicion(indice:number, objEvento){
        const CTRL = this.datosRegla.get('condicionesGlobales') as FormArray;
        let origen      : string = (objEvento['origen']) ? objEvento['origen'] : '';
        let id          : string = (objEvento['xde_id']) ? objEvento['xde_id'] + '-' + origen : '';
        let descripcion : string = (objEvento['xde_descripcion']) ? objEvento['xde_descripcion'] : '';

        if(objEvento) {
            CTRL.controls[indice].get('origen').setValue(origen, {emitEvent:false});
            CTRL.controls[indice].get('xde_id').setValue(id, {emitEvent:false});
            CTRL.controls[indice].get('xde_descripcion').setValue(descripcion, {emitEvent:false});
        } else {
            CTRL.controls[indice].get('origen').reset();
            CTRL.controls[indice].get('xde_id').reset();
            CTRL.controls[indice].get('xde_descripcion').reset();
        }

        this.agregarRequeridosCondicionGlobal(indice);
    }

    // Fin Autocomplete Condicion

    // Inicio Autocomplete Accion

    /**
     * Evalua los cambios en el select de acción.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    agregarDescripcionXpath(event) {
        if (event.id == 'NOTIFICAR') {
            this.valueChangesAccion();
            this.datosAccion.get('ate_accion_titulo').setValidators([Validators.required]);
            this.datosAccion.get('accion_origen').setValidators([Validators.required]);
            this.datosAccion.get('xde_accion_id').setValidators([Validators.required]);
            this.datosAccion.get('xde_accion_descripcion').setValidators([Validators.required]);
        } else {
            if (this.subscriptionsAccion && this.subscriptionsAccion.length > 0) {
                this.subscriptionsAccion.forEach(s => s.unsubscribe());
            }
            this.datosAccion.get('ate_accion_titulo').clearValidators();
            this.datosAccion.get('accion_origen').clearValidators();
            this.datosAccion.get('xde_accion_id').clearValidators();
            this.datosAccion.get('xde_accion_descripcion').clearValidators();
            this.datosAccion.get('ate_accion_titulo').reset();
            this.datosAccion.get('accion_origen').reset();
            this.datosAccion.get('xde_accion_id').reset();
            this.datosAccion.get('xde_accion_descripcion').reset();
        }
    }

    /**
     * Evalua los cambios en el autoComplete de la sección accion.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    valueChangesAccion(){
        this.subscriptionsAccion.push(
            this.datosAccion.get('xde_accion_descripcion')
            .valueChanges
            .pipe(
                filter(value => value.length >= 1),
                debounceTime(1000),
                distinctUntilChanged(),
                tap(() => {
                    this.loading(true);
                    this.resultadosAutocomplete['accion'] = [];
                    this.noCoincidences = true;
                    this.datosAccion.get('xde_accion_descripcion').disable();
                }),
                switchMap(value =>
                    this._configuracionService.obtenerCondiciones(value, this.ate_aplica_para.value, this.ofe_identificacion.value)
                        .pipe(
                            finalize(() => {
                                this.loading(false);
                                this.datosAccion.get('xde_accion_descripcion').enable();
                            })
                        )
                )
            )
            .subscribe(res => {
                this.resultadosAutocomplete['accion'] = res.data;
                if (this.resultadosAutocomplete['accion'].length <= 0) {
                    this.datosAccion.get('accion_origen').setValue('');
                    this.noCoincidences = true;
                } else {
                    this.noCoincidences = false;
                }
            })
        );
    }

    /**
     * Establece los valores para origen, id y descripcion cuando un item es seleccionado en un autocomplete.
     *
     * @param {object} registroSeleccionado
     * @memberof ProductosGestionarComponent
     */
    setValorAccion(objEvento){
        let accion_origen     : string = (objEvento['accion_origen']) ? objEvento['accion_origen'] : '';
        let accion_id         : string = (objEvento['xde_accion_id']) ? objEvento['xde_accion_id'] + '-' + accion_origen : '';
        let accion_descripcion: string = (objEvento['xde_accion_descripcion']) ? objEvento['xde_accion_descripcion'] : '';

        if(objEvento) {
            this.datosAccion.get('accion_origen').setValue(accion_origen, {emitEvent:false});
            this.datosAccion.get('xde_accion_id').setValue(accion_id, {emitEvent:false});
            this.datosAccion.get('xde_accion_descripcion').setValue(accion_descripcion, {emitEvent:false});
        } else {
            this.datosAccion.get('accion_origen').reset();
            this.datosAccion.get('xde_accion_id').reset();
            this.datosAccion.get('xde_accion_descripcion').reset();
        }
    }

    // Fin Autocomplete Accion

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    public buildErrorsObject() {
        this.formErrors = {
            ate_erp: {
                required: 'El ERP es requerido!'
            },
            ate_descripcion: {
                required: 'La Descripción es requerida!'
            },
            ate_aplica_para: {
                required: 'El Aplica Para es requerido!'
            },
            xde_descripcion: {
                required: 'La Descripción del Xpath es requerida!'
            },
            ate_condicion: {
                required: 'La Condición es requerida!'
            },
            ate_valor: {
                required: 'El Valor es requerido!'
            },
            ate_deben_aplica: {
                required: 'El Deben Aplicar es requerido!'
            },
            ate_accion: {
                required: 'La Acción es requerida!'
            },
            ate_accion_titulo: {
                required: 'El Título de la Acción es requerido!'
            },
            xde_accion_descripcion: {
                required: 'La Descripción del Xpath es requerida!'
            }
        };
    }

    /**
     * Se encarga de cargar los datos de una Administración Recepción ERP que se ha seleccionado en el tracking.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    public loadAdministracionRecepcionErp(): void {
        this.loading(true);
        this._configuracionService.get(this._ate_grupo).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    let controlEstado: FormControl = new FormControl('', Validators.required);
                    this.form.addControl('estado', controlEstado);
                    this.estado = this.form.controls['estado'];
                    
                    this.ofe_identificacion.setValue(res.data.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion);
                    this.ate_erp.setValue(res.data.ate_erp);
                    this.ate_descripcion.setValue(res.data.ate_descripcion);
                    this.ate_aplica_para.setValue(res.data.ate_aplica_para.split(','));
                    this.ate_deben_aplica.setValue(res.data.ate_deben_aplica);
                    this.ate_accion.setValue(res.data.ate_accion);
                    this.ate_accion_titulo.setValue(res.data.ate_accion_titulo);
                    this.accion_origen.setValue(res.data.accion_origen);
                    this.xde_accion_id.setValue(res.data.xde_accion_id);
                    this.xde_accion_descripcion.setValue(res.data.xde_accion_descripcion, {emitEvent:false});

                    // Sección de Condiciones
                    let index = 0;
                    const CTRL = this.datosRegla.get('condicionesGlobales') as FormArray;
                    res.data.condicionesGlobales.forEach(condicion => {
                        CTRL.controls[index].get('ate_id').setValue(condicion.ate_id);
                        CTRL.controls[index].get('origen').setValue(condicion.origen);
                        CTRL.controls[index].get('xde_id').setValue(condicion.xde_id);
                        CTRL.controls[index].get('xde_descripcion').setValue(condicion.xde_descripcion, {emitEvent:false});
                        CTRL.controls[index].get('ate_condicion').setValue(condicion.ate_condicion);
                        CTRL.controls[index].get('ate_valor').setValue(condicion.ate_valor);
                        this.agregarCamposCondicionGlobal();
                        index++;
                    });

                    this.eliminarCondicionGlobal(index);

                    if (res.data.estado === 'ACTIVO') {
                        this.estado.setValue('ACTIVO');
                    } else {
                        this.estado.setValue('INACTIVO');
                    }
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;
                    this.valueChangesAccion();
                }
            },
            error => {
                this.loading(false);
                let texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar la Administración Recepción ERP', 'Ok', 'btn btn-danger', 'configuracion/administracion-recepcion-erp', this._router);
            }
        );
    }

    /**
     * Construcción del formulario principal.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    buildFormulario() {
        this.form = this._formBuilder.group({
            DatosGenerales: this.getFormularioDatosGenerales(),
            DatosRegla    : this.getFormularioDatosRegla(),
            DatosAccion   : this.getFormularioDatosAccion()
        });
    }

    /**
     * Construccion del formulario de datos de acción.
     *
     */
    getFormularioDatosGenerales() {
        this.datosGenerales = this._formBuilder.group({
            ofe_identificacion: this.requerido(),
            ate_erp           : this.requerido(),
            ate_descripcion   : this.requerido(),
            ate_aplica_para   : this.requerido()
        });
        this.ofe_identificacion = this.datosGenerales.controls['ofe_identificacion'];
        this.ate_erp            = this.datosGenerales.controls['ate_erp'];
        this.ate_descripcion    = this.datosGenerales.controls['ate_descripcion'];
        this.ate_aplica_para    = this.datosGenerales.controls['ate_aplica_para'];

        return this.datosGenerales;
    }

    /**
     * Construccion del formulario de datos de reglas.
     *
     */
    getFormularioDatosRegla() {
        this.datosRegla = this._formBuilder.group({
            ate_deben_aplica   : this.requerido(),
            condicionesGlobales: this._formBuilder.array([])
        });
        this.ate_deben_aplica = this.datosRegla.controls['ate_deben_aplica'];

        this.agregarCamposCondicionGlobal();

        return this.datosRegla;
    }

    /**
     * Construccion del formulario de datos de acción.
     *
     */
    getFormularioDatosAccion() {
        this.datosAccion = this._formBuilder.group({
            ate_accion            : this.requerido(),
            ate_accion_titulo     : [''],
            accion_origen         : [''],
            xde_accion_id         : [''],
            xde_accion_descripcion: ['']
        });
        this.ate_accion             = this.datosAccion.controls['ate_accion'];
        this.ate_accion_titulo      = this.datosAccion.controls['ate_accion_titulo'];
        this.accion_origen          = this.datosAccion.controls['accion_origen'];
        this.xde_accion_id          = this.datosAccion.controls['xde_accion_id'];
        this.xde_accion_descripcion = this.datosAccion.controls['xde_accion_descripcion'];

        return this.datosAccion;
    }

    /**
     * Permite regresar a la lista de administración recepción ERP.
     *
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    regresar() {
        this._router.navigate(['configuracion/administracion-recepcion-erp']);
    }

    /**
     * Inicializa la data necesaria para la construcción de la Administración Recepción ERP.
     *
     * @private
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getOfes("recepcion=true").subscribe(
            result => {
                this.ofes = result.data.ofes;
                this.erp = result.data.erp ? JSON.parse(result.data.erp) : [];
            
                if(this.ofes.length === 1)
                    this.ofe_identificacion.setValue(this.ofes[0].ofe_identificacion);

                if(this.erp.length === 1)
                    this.ate_erp.setValue(this.erp[0]);

                if (this.editar) {
                    let controlEstado: FormControl = new FormControl('', Validators.required);
                    this.form.addControl('estado', controlEstado);
                    this.estado = this.form.controls['estado'];
                    this.loadAdministracionRecepcionErp();
                } else if (this.ver){
                    this.loadAdministracionRecepcionErp();
                } else {
                    this.loading(false);
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
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    public resourceAdministracionRecepcionErp(values) {
        let payload = this.getPayload(values);

        this.loading(true);
        
        if (this.form.valid) {
            if (this._ate_grupo) {
                this._configuracionService.update(payload, this._ate_grupo).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Administración Recepción ERP actualizada exitosamente', 'Ok', 'btn btn-success', `/configuracion/administracion-recepcion-erp`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el registro de Administración Recepción ERP', 'Ok', 'btn btn-danger');
                    }
                );
            } else {
                this._configuracionService.create(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Administración Recepción ERP creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/administracion-recepcion-erp`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el registro de Administración Recepción ERP', 'Ok', 'btn btn-danger');
                    }
                );
            }
        }
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     * @return {*} 
     * @memberof AdministracionRecepcionErpGestionarComponent
     */
    getPayload(values){
        let payload = {
            "ofe_identificacion"    : values.DatosGenerales.ofe_identificacion,
            "ate_erp"               : values.DatosGenerales.ate_erp,
            "ate_descripcion"       : values.DatosGenerales.ate_descripcion,
            "ate_aplica_para"       : values.DatosGenerales.ate_aplica_para.join(','),
            "ate_deben_aplica"      : values.DatosRegla.ate_deben_aplica,
            "ate_accion"            : values.DatosAccion.ate_accion,
            "ate_accion_titulo"     : values.DatosAccion.ate_accion_titulo,
            "accion_origen"         : values.DatosAccion.accion_origen,
            "xde_accion_id"         : values.DatosAccion.xde_accion_id,
            "xde_accion_descripcion": values.DatosAccion.xde_accion_descripcion,
            "condicionesGlobales"   : values.DatosRegla.condicionesGlobales,
            "estado"                : this.estado && this.estado.value ? this.estado.value : null,
            "ids_eliminar"          : this.idsEliminados.filter(value => value !== undefined)
        };

        return payload;
    }
}
