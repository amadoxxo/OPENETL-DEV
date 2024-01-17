import {Component, OnDestroy, OnInit} from '@angular/core';
import {BaseComponentView} from '../../../core/base_component_view';
import {ActivatedRoute, Router} from '@angular/router';
import {AbstractControl, FormControl, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {Subject} from 'rxjs';
import * as moment from 'moment';
import {CommonsService} from '../../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {ParametrosService} from '../../../../services/parametros/parametros.service';
import {JwtHelperService} from '@auth0/angular-jwt';

@Component({
    selector: 'app-software-proveedor-tecnologico-gestionar',
    templateUrl: './software-proveedor-tecnologico-gestionar.component.html',
    styleUrls: ['./software-proveedor-tecnologico-gestionar.component.scss']
})
export class SoftwareProveedorTecnologicoGestionarComponent extends BaseComponentView implements OnInit, OnDestroy {
    
    // Usuario en línea
    public usuario: any;
    public objMagic = {};
    public ver: boolean;
    public editar: boolean;
    public titulo: string;

    // Formulario y controles
    public form                                  : FormGroup;
    public sft_id                                : AbstractControl;
    public sft_identificador                     : AbstractControl;
    public sft_pin                               : AbstractControl;
    public sft_nombre                            : AbstractControl;
    public sft_fecha_registro                    : AbstractControl;
    public sft_nit_proveedor_tecnologico         : AbstractControl;
    public sft_razon_social_proveedor_tecnologico: AbstractControl;
    public add_id                                : AbstractControl;
    public sft_aplica_para                       : AbstractControl;
    public sft_testsetid                         : AbstractControl;
    public estado                                : AbstractControl;
    public ambienteSeleccionado                  : number;
    public maxDate                               = new Date();
    public ambienteDestinoSeleccionado           : string;

    public formErrors: any;

    recurso: string = 'Software Proveedor Tecnológico';
    public sftAplicaParaSeleccionado;
    public sftAplicaParaItems = [
        {
            sft_aplica_para : 'DE',
            sft_aplica_para_descripcion : 'DE - Documento Electrónico'
        },
        {
            sft_aplica_para : 'DS',
            sft_aplica_para_descripcion : 'DS - Documento Soporte'
        },
        {
            sft_aplica_para : 'DN',
            sft_aplica_para_descripcion : 'DN - Documento Nomina Electrónica'
        }
    ];
    _sft_id: any;
    _sft_identificador: any;
    public ambientes: Array<any> = [];

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
        private _parametrosService: ParametrosService,
        private jwtHelperService: JwtHelperService
    ) {
        super();
        this._configuracionService.setSlug = "spt";
        this.init();
        this.buildErrorsObject();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    ngOnInit() {
        this._sft_id = this._route.snapshot.params['sft_id'];
        this._sft_identificador = this._route.snapshot.params['sft_identificador'];
        this.ver = false;
        if (this._router.url.indexOf('editar-software-proveedor-tecnologico') !== -1) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
        } else if (this._router.url.indexOf('ver-software-proveedor-tecnologico') !== -1) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true
        } else {
            this.titulo = 'Crear ' + this.recurso;
        }
        this.initForBuild();
        if(this.ver){
            this.sft_fecha_registro.disable();
            this.add_id.disable();
            this.sft_aplica_para.disable();
            this.sft_testsetid.disable();
        }
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObject() {
        this.formErrors = {
            sft_identificador: {
                required: 'El Identificador es requerido!',
                maxLength: 'Ha introducido más de 255 caracteres'
            },
            sft_pin: {
                required: 'El Pin es requerido!',
                maxLength: 'Ha introducido más de 100 caracteres'
            },
            sft_nombre: {
                required: 'El Nombre es requerido!',
                maxLength: 'Ha introducido más de 255 caracteres'
            },
            sft_testsetid: {
                required: 'El SetTestId es requerido!',
                maxLength: 'Ha introducido más de 255 caracteres'
            },
            sft_aplica_para: {
                required: 'El Aplica Para es requerido!'
            },
            sft_nit_proveedor_tecnologico: {
                required: 'El NIT es requerido!',
                maxLength: 'Ha introducido más de 20 caracteres'
            },
            sft_razon_social_proveedor_tecnologico: {
                required: 'La razon social es requerida!',
                maxLength: 'Ha introducido más de 255 caracteres'
            },
            sft_fecha_registro: {
                required: 'La Fecha de Registro es requerida!'
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
     * Se encarga de cargar los datos de un spt que se ha seleccionado en el tracking.
     * 
     */
    public loadSpt(): void {
        this.loading(true);
        this._configuracionService.get(this._sft_id).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    let controlEstado: FormControl = new FormControl('', Validators.required);
                    this.form.addControl('estado', controlEstado);
                    this.estado = this.form.controls['estado'];

                    this.sft_id.setValue(res.data.sft_id);
                    this.sft_identificador.setValue(res.data.sft_identificador);
                    this.sft_nombre.setValue(res.data.sft_nombre);
                    this.sft_pin.setValue(res.data.sft_pin);
                    this.sft_nombre.setValue(res.data.sft_nombre);
                    this.sft_fecha_registro.setValue(res.data.sft_fecha_registro);
                    this.sft_nit_proveedor_tecnologico.setValue(res.data.sft_nit_proveedor_tecnologico);
                    this.sft_razon_social_proveedor_tecnologico.setValue(res.data.sft_razon_social_proveedor_tecnologico);
                    if(res.data.get_ambiente_destino) {
                        this.add_id.setValue(res.data.get_ambiente_destino.add_id);
                        this.cambioAmbiente(res.data.get_ambiente_destino);
                        setTimeout(() => {
                            if(this.ambienteDestinoSeleccionado === 'SendTestSetAsync')
                                this.sft_testsetid.setValue(res.data.sft_testsetid);
                        }, 300);
                    }
                    if(res.data.sft_aplica_para !== null && res.data.sft_aplica_para !== ''){
                        this.sftAplicaParaSeleccionado = res.data.sft_aplica_para.split(',');
                    }
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
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Proveedor Tecnológico', 'Ok', 'btn btn-danger', 'configuracion/software-proveedor-tecnologico', this._router);
            }
        );
    }

    /**
     * Construccion del formulario principal.
     * 
     */
    buildFormulario() {
        this.form = this._formBuilder.group({
            sft_id                                : [''],
            sft_identificador                     : this.requeridoMaxlong(255),
            sft_pin                               : this.requeridoMaxlong(100),
            sft_nombre                            : this.requeridoMaxlong(255),
            sft_fecha_registro                    : [''],
            add_id                                : this.requerido(),
            sft_aplica_para                       : this.requerido(),
            sft_nit_proveedor_tecnologico         : this.requeridoMaxlong(20),
            sft_razon_social_proveedor_tecnologico: this.requeridoMaxlong(255),
            sft_testsetid                         : [''],
            estado                                : ['']
            
        });
        this.sft_id                                 = this.form.controls['sft_id'];
        this.sft_identificador                      = this.form.controls['sft_identificador'];
        this.sft_pin                                = this.form.controls['sft_pin'];
        this.sft_nombre                             = this.form.controls['sft_nombre'];
        this.sft_fecha_registro                     = this.form.controls['sft_fecha_registro'];
        this.add_id                                 = this.form.controls['add_id'];
        this.sft_aplica_para                        = this.form.controls['sft_aplica_para'];
        this.sft_testsetid                          = this.form.controls['sft_testsetid'];
        this.sft_nit_proveedor_tecnologico          = this.form.controls['sft_nit_proveedor_tecnologico'];
        this.sft_razon_social_proveedor_tecnologico = this.form.controls['sft_razon_social_proveedor_tecnologico'];
    }

    /**
     * Permite regresar a la lista de proveedores tecnológicos.
     * 
     */
    regresar() {
        this._router.navigate(['configuracion/software-proveedor-tecnologico']);
    }

    /**
     * Inicializa la data necesaria para la construcción del proveedor tecnologico.
     * 
     */
    private initForBuild() {
        this.loading(true);
        this._parametrosService.listarSelect('ambiente-destino-documentos').subscribe(
            result => {
                this.ambientes = result.data;
                for(let i = 0; i < this.ambientes.length; i++)
                    this.ambientes[i].add_codigo_descripcion = this.ambientes[i].add_codigo + ' - ' + this.ambientes[i].add_descripcion;
                if (this._sft_identificador)
                    this.loadSpt();
                else    
                    this.loading(false);
            }, error => {
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los ambientes', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Crea o actualiza un nuevo registro.
     * 
     * @param values
     */
    public resourceSpt(values) {
        let payload = this.getPayload();
        this.loading(true);
        let that = this;
        
        if (this.form.valid) {
            if (this._sft_identificador) {
                payload['estado'] =  this.estado.value;
                this._configuracionService.update(payload, this._sft_id).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Software Proveedor Tecnológico actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/software-proveedor-tecnologico`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el Software Proveedor Tecnológico', 'Ok', 'btn btn-danger');
                    });
            } else {
                this._configuracionService.create(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Software Proveedor Tecnológico creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/software-proveedor-tecnologico`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el Software Proveedor Tecnológico', 'Ok', 'btn btn-danger');
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
            "sft_id"                                : this.sft_id.value,
            "sft_identificador"                     : this.sft_identificador.value,
            "sft_nombre"                            : this.sft_nombre.value,
            "sft_pin"                               : this.sft_pin.value,
            "sft_aplica_para"                       : this.sftAplicaParaSeleccionado.join(),
            "sft_fecha_registro"                    : moment(this.sft_fecha_registro.value).format('YYYY-MM-DD'),
            "sft_nit_proveedor_tecnologico"         : this.sft_nit_proveedor_tecnologico.value,
            "sft_razon_social_proveedor_tecnologico": this.sft_razon_social_proveedor_tecnologico.value,
            "add_id"                                : this.add_id.value,
            "sft_testsetid"                         : this.sft_testsetid.value
        };
        return payload;
    }
    /**
     * Identifica el cambio realizado en el combo de ambiente de destino del documento
     *
     * @param ambiente event
     * @memberof SoftwareProveedorTecnologicoGestionarComponent
     */
    cambioAmbiente(ambiente) {
        this.ambienteDestinoSeleccionado = (ambiente !== undefined) ?ambiente.add_metodo : '';
    }
}
