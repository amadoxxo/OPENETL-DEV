import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {BaseComponentView} from '../../../core/base_component_view';
import {ActivatedRoute, Router} from '@angular/router';
import {AbstractControl, FormArray, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {Subject} from 'rxjs';
import * as moment from 'moment';
import {CommonsService} from '../../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import { JwtHelperService } from '@auth0/angular-jwt';
import { MatAccordion } from '@angular/material/expansion';

@Component({
    selector: 'app-resoluciones-facturacion-gestionar',
    templateUrl: './resoluciones-facturacion-gestionar.component.html',
    styleUrls: ['./resoluciones-facturacion-gestionar.component.scss']
})
export class ResolucionesFacturacionGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion') acordion: MatAccordion;

    // Usuario en línea
    public usuario  : any;
    public objMagic = {};
    public ver      : boolean;
    public editar   : boolean;
    public mostrarDatosTransmisionDian: boolean = true;
    
    // Formulario y controles
    public form                       : FormGroup;
    public ofe_id                     : AbstractControl;
    public rfa_resolucion             : AbstractControl;
    public rfa_prefijo                : AbstractControl;
    public rfa_clave_tecnica          : AbstractControl;
    public rfa_tipo                   : AbstractControl;
    public rfa_fecha_desde            : AbstractControl;
    public rfa_fecha_hasta            : AbstractControl;
    public rfa_consecutivo_inicial    : AbstractControl;
    public rfa_consecutivo_final      : AbstractControl;
    public cdo_control_consecutivos   : AbstractControl;
    public cdo_consecutivo_provisional: AbstractControl;
    public rfa_dias_aviso             : AbstractControl;
    public rfa_consecutivos_aviso     : AbstractControl;
    public estado                     : AbstractControl;
    
    public titulo: string;

    public ofes: Array<any> = [];
    public arrTipos: Array<Object> = [
        { id: 'AUTORIZACION', nombre: 'AUTORIZACION' },
        { id: 'HABILITACION', nombre: 'HABILITACION' },
        { id: 'CONTINGENCIA', nombre: 'CONTINGENCIA' },
        { id: 'DOCUMENTO_SOPORTE', nombre: 'DOCUMENTO SOPORTE' }
    ];

    // public ofeObjeto = {};

    proveedores: FormArray;
    motivos: FormArray;
    observaciones: FormArray;

     // Mínimo de fechas
    public maxDate = new Date();
    public minDateFechaFin = new Date();
    public maxDateFechaFin = new Date('9999-12-31');

    recurso: string = 'Resolución de Facturación';

    _rfa_id: any;
    _rfa_resolucion: any;

    // Steppers
    ofeSelector          : FormGroup;
    datosGenerales       : FormGroup;
    datosTransmision     : FormGroup;
    controlConsecutivos  : FormGroup;
    vencimientoResolucion: FormGroup;

    public formErrors: any;

    objOFE = null;
    usuarioCreador = null;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    /**
     *
     * @param _router
     * @param _route
     * @param _formBuilder
     * @param _commonsService
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
        this._configuracionService.setSlug = "resoluciones-facturacion";
        this.init();
        this.buildErrorsObject();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    /**
     * Vista construida
     */
    ngAfterViewInit() {
        if (this.ver)
            this.acordion.openAll();
    }

    ngOnInit() {
        this._rfa_id = this._route.snapshot.params['rfa_id'];
        this._rfa_resolucion = this._route.snapshot.params['rfa_prefijo_resolucion'];
        this.ver = false;
        if (this._rfa_resolucion && !this._rfa_id) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
        } else if (this._rfa_resolucion && this._rfa_id) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true;
        } else {
            this.titulo = 'Crear ' + this.recurso;
        }
        this.initForBuild();
        if(this.ver){
            this.rfa_fecha_desde.disable();
            this.rfa_fecha_hasta.disable();
        }
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObject() {
        this.formErrors = {
            rfa_resolucion: {
                required: 'El Número de Resolución es requerido!',
                maxLength: 'Ha introducido más de 20 caracteres'
            },
            rfa_prefijo: {
                required: 'El Prefijo es requerido!',
                maxLength: 'Ha introducido más de 4 caracteres'
            },
            rfa_fecha_desde: {
                required: 'La Vigencia Desde es requerida!',
            },
            rfa_fecha_hasta: {
                required: 'La Vigencia Hasta es requerida!',
            },
            rfa_clave_tecnica: {
                required: 'La Clave Técnica es requerida!',
            },
            rfa_tipo: {
                required: 'El tipo es requerido!',
            },
            rfa_consecutivo_inicial: {
                required: 'El Consecutivo Inicial es requerido!',
                maxLength: 'Ha introducido más de 20 caracteres'
            },
            rfa_consecutivo_final: {
                required: 'El Consecutivo Final es requerido!',
                maxLength: 'Ha introducido más de 20 caracteres'
            },
            rfa_dias_aviso: {
                maxLength: 'Ha introducido más de 4 caracteres'
            },
            rfa_consecutivos_aviso: {
                maxLength: 'Ha introducido más de 20 caracteres'
            }
        };
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
        this.buildFormulario();
    }

    /**
     * Se encarga de cargar los datos de una resolución de facturación que se ha seleccionado en el tracking.
     * 
     */
    public loadRFA(): void {
        this.loading(true);
        this._configuracionService.get(this._rfa_resolucion).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    if (res.data.get_configuracion_obligado_facturar_electronicamente)
                        this.ofe_id.setValue(res.data.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion);
                    this.rfa_resolucion.setValue(res.data.rfa_resolucion);
                    this.rfa_prefijo.setValue(res.data.rfa_prefijo);
                    this.rfa_clave_tecnica.setValue(res.data.rfa_clave_tecnica ? res.data.rfa_clave_tecnica : '');
                    this.rfa_tipo.setValue((res.data.rfa_tipo == null) ? 'AUTORIZACION' : res.data.rfa_tipo);
                    this.minDateFechaFin = moment(res.data.rfa_fecha_desde).toDate();
                    this.rfa_fecha_desde.setValue(res.data.rfa_fecha_desde);
                    this.rfa_fecha_hasta.setValue(res.data.rfa_fecha_hasta);
                    this.rfa_consecutivo_inicial.setValue(res.data.rfa_consecutivo_inicial);
                    this.rfa_consecutivo_final.setValue(res.data.rfa_consecutivo_final);
                    this.cdo_control_consecutivos.setValue(res.data.cdo_control_consecutivos === 'SI' ? 'SI' : 'NO');
                    this.cdo_consecutivo_provisional.setValue(res.data.cdo_consecutivo_provisional === 'SI' ? 'SI' : 'NO');
                    this.rfa_dias_aviso.setValue(res.data.rfa_dias_aviso);
                    this.rfa_consecutivos_aviso.setValue(res.data.rfa_consecutivos_aviso);
                    this.estado.setValue(res.data.estado);
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;

                    this.seleccionTipoResolucion(this.rfa_tipo.value, false);

                    if(this.cdo_control_consecutivos.value === 'NO') {
                        this.cdo_consecutivo_provisional.setValue('NO');
                        this.cdo_consecutivo_provisional.disable({onlySelf: true});
                    } else {
                        this.cdo_consecutivo_provisional.enable({onlySelf: true});
                    }
                }
            },
            error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar la Resolución de Facturación');
            }
        );
    }

    /**
     * Construccion del formulario principal.
     * 
     */
    buildFormulario() {
        this.form = this._formBuilder.group({
            DatosOfe             : this.buildFormularioDatosOfe(),
            DatosGenerales       : this.buildFormularioDatosGenerales(),
            DatosTransmision     : this.buildFormularioDatosTransmision(),
            ControlConsecutivos  : this.buildFormularioControlConsecutivos(),
            VencimientoResolucion: this.buildFormularioVencimientoResolucion()
        });
    }

    /**
     * Construcción de los datos del del OFE.
     * 
     */
    buildFormularioDatosOfe() {
        this.ofeSelector = this._formBuilder.group({
            ofe_id: this.requerido(),
        });
        this.ofe_id = this.ofeSelector.controls['ofe_id'];
        return this.ofeSelector;
    }

    /**
     * Construcción de los datos generales de la Resolución de Facturación.
     * 
     */
    buildFormularioDatosGenerales() {
        this.datosGenerales = this._formBuilder.group({
            rfa_resolucion             : this.requeridoMaxlong(20),
            rfa_prefijo                : [''],
            rfa_tipo                   : this.requerido(),
            rfa_fecha_desde            : this.requerido(),
            rfa_fecha_hasta            : this.requerido(),
            rfa_consecutivo_inicial    : this.requerido(),
            rfa_consecutivo_final      : this.requerido(),
            rfa_dias_aviso             : [''],
            rfa_consecutivos_aviso     : [''],
            estado                     : ['']
        });
        this.rfa_resolucion              = this.datosGenerales.controls['rfa_resolucion'];
        this.rfa_prefijo                 = this.datosGenerales.controls['rfa_prefijo'];
        this.rfa_tipo                    = this.datosGenerales.controls['rfa_tipo'];
        this.rfa_fecha_desde             = this.datosGenerales.controls['rfa_fecha_desde'];
        this.rfa_fecha_hasta             = this.datosGenerales.controls['rfa_fecha_hasta'];
        this.rfa_consecutivo_inicial     = this.datosGenerales.controls['rfa_consecutivo_inicial'];
        this.rfa_consecutivo_final       = this.datosGenerales.controls['rfa_consecutivo_final'];
        this.rfa_dias_aviso              = this.datosGenerales.controls['rfa_dias_aviso'];
        this.rfa_consecutivos_aviso      = this.datosGenerales.controls['rfa_consecutivos_aviso'];
        this.estado                      = this.datosGenerales.controls['estado'];
        return this.datosGenerales;
    }

    /**
     * Construcción de los datos generales de la Resolución de Facturación.
     * 
     */
    buildFormularioDatosTransmision() {
        this.datosTransmision = this._formBuilder.group({
            rfa_clave_tecnica: this.requerido()
        });
        this.rfa_clave_tecnica = this.datosTransmision.controls['rfa_clave_tecnica'];
        return this.datosTransmision;
    }

    /**
     * Construcción de control de consecutivos de la Resolución de Facturación.
     * 
     */
    buildFormularioControlConsecutivos() {
        this.controlConsecutivos = this._formBuilder.group({
            cdo_control_consecutivos   : [''],
            cdo_consecutivo_provisional: ['']
        });
        this.cdo_control_consecutivos    = this.controlConsecutivos.controls['cdo_control_consecutivos'];
        this.cdo_consecutivo_provisional = this.controlConsecutivos.controls['cdo_consecutivo_provisional'];
        return this.controlConsecutivos;
    }

    /**
     * Construcción de la configuración de vencimiento de la Resolución de Facturación.
     *
     * @memberof ResolucionesFacturacionGestionarComponent
     */
    buildFormularioVencimientoResolucion() {
        this.vencimientoResolucion = this._formBuilder.group({
            rfa_dias_aviso        : [''],
            rfa_consecutivos_aviso: ['']
        });
        this.rfa_dias_aviso         = this.vencimientoResolucion.controls['rfa_dias_aviso'];
        this.rfa_consecutivos_aviso = this.vencimientoResolucion.controls['rfa_consecutivos_aviso'];
        return this.vencimientoResolucion;
    }

    /**
     * Permite regresar a la lista de oferentes.
     * 
     */
    regresar() {
        this._router.navigate(['configuracion/resoluciones-facturacion']);
    }

    /**
     * Crea o actualiza un nuevo registro.
     * 
     * @param values
     */
    public resourceRFA(values) {
        let payload = this.getPayload();
        this.loading(true);
        let that = this;
        
        if (this.form.valid) {
            if (this._rfa_resolucion) {
                payload['estado'] =  this.estado.value;
                this._configuracionService.update(payload, this._rfa_resolucion).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Resolución de Facturación actualizada exitosamente', 'Ok', 'btn btn-success', `/configuracion/resoluciones-facturacion`, this._router);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al actualizar la Resolución de Facturación');
                    });
            } else {
                this._configuracionService.create(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Resolución de Facturación creada exitosamente', 'Ok', 'btn btn-success', `/configuracion/resoluciones-facturacion`, this._router);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al guardar la Resolución de Facturación');
                    });
                }
        }
    }

    /**
     * Crea un json para enviar los campos del formulario.
     * 
     */
    getPayload(){
        let payload = {
            "ofe_identificacion"         : this.ofe_id.value,
            "rfa_resolucion"             : this.rfa_resolucion.value,
            "rfa_prefijo"                : this.rfa_prefijo.value, 
            "rfa_clave_tecnica"          : this.rfa_clave_tecnica.value ? this.rfa_clave_tecnica.value : '',
            "rfa_tipo"                   : this.rfa_tipo.value,
            "rfa_fecha_desde"            : moment(this.rfa_fecha_desde.value).format('YYYY-MM-DD'),
            "rfa_fecha_hasta"            : moment(this.rfa_fecha_hasta.value).format('YYYY-MM-DD'),
            "rfa_consecutivo_inicial"    : this.rfa_consecutivo_inicial.value,
            "rfa_consecutivo_final"      : this.rfa_consecutivo_final.value,
            "cdo_control_consecutivos"   : this.cdo_control_consecutivos.value,
            "cdo_consecutivo_provisional": this.cdo_consecutivo_provisional.value,
            "rfa_dias_aviso"             : this.rfa_dias_aviso.value,
            "rfa_consecutivos_aviso"     : this.rfa_consecutivos_aviso.value
        }

        return payload;
    }

    setMinDateFechaFin (fechaIni) {
        this.rfa_fecha_hasta.setValue(null);
        this.minDateFechaFin = fechaIni;
    }

    /**
     * Inicializa la data necesaria para la construccion del adquirente.
     * 
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false').subscribe(
            result => {
                this.ofes = result.data.ofes;
                if (this._rfa_resolucion)
                    this.loadRFA();
                else    
                    this.loading(false);
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los OFEs', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Permite establecer acciones en el formulario de acuerdo al valor seleccionado
     *
     * @param {string} valor Valor seleccionado
     * @param {boolean} expandir Indica cuando se deben expandir o no los acordeones
     * @memberof ResolucionesFacturacionGestionarComponent
     */
    seleccionTipoResolucion(valor: string, expandir: boolean) {
        if (valor !== 'DOCUMENTO_SOPORTE' && valor !== 'CONTINGENCIA') {
            // La clave técnica debe ser obligatoria
            this.mostrarDatosTransmisionDian = true;
            this.rfa_clave_tecnica.setValidators([Validators.required]);
            let that = this;
            if(expandir)
                setTimeout(() => { that.acordion.openAll(); }, 300);
        } else {
            // La clave técnica no debe ser obligatoria
            this.mostrarDatosTransmisionDian = false;
            this.rfa_clave_tecnica.setValue('');
            this.rfa_clave_tecnica.setValidators(null);
        }
        this.datosTransmision.controls['rfa_clave_tecnica'].updateValueAndValidity();
    }

    /**
     * Dependiendo de la selección de aplica control de consecutivos se habilita o no el campo de consecutivo provisional
     *
     * @param string valor Valor seleccionado para control de consecutivos
     * @memberof ResolucionesFacturacionGestionarComponent
     */
    changeAplicaControlConsecutivos(valor) {
        if(valor === 'NO') {
            this.cdo_consecutivo_provisional.setValue('NO');
            this.cdo_consecutivo_provisional.disable({onlySelf: true});
        } else {
            this.cdo_consecutivo_provisional.enable({onlySelf: true});
        }
    }
}
