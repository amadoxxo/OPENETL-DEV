import {Component, Inject, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormArray, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {ParametrosService} from '../../../services/parametros/parametros.service';
import {BaseService} from '../../../services/core/base.service';
import {RadianService} from '../../../services/radian/radian.service';
import {DocumentosRecibidosService} from '../../../services/recepcion/documentos_recibidos.service';
import {BaseComponentView} from 'app/main/core/base_component_view';
import {DatosParametricosValidacionService} from './../../../services/proyectos-especiales/recepcion/fnc/validacion/datos-parametricos-validacion.service';
import {ValidacionDocumentosService} from "./../../../services/recepcion/validacion_documentos.service";
import * as capitalize from 'lodash';
import * as moment from 'moment';

import { MomentDateAdapter} from '@angular/material-moment-adapter';
import { DateAdapter, MAT_DATE_FORMATS, MAT_DATE_LOCALE } from '@angular/material/core';

export const MY_FORMATS = {
    parse: {
        dateInput: 'YYYY-MM-DD',
    },
    display: {
        dateInput: 'YYYY-MM-DD',
        monthYearLabel: 'YYYY MMM ',
        dateA11yLabel: 'LL',
        monthYearA11yLabel: 'YYYY MMMM',
    },
};

@Component({
    selector: 'app-modal-rechazo-documentos',
    templateUrl: './modal-eventos-documentos.component.html',
    styleUrls: ['./modal-eventos-documentos.component.scss'],
    providers: [
        {provide: MAT_DATE_LOCALE, useValue: 'es-ES'},
        {provide: DateAdapter, useClass: MomentDateAdapter, deps: [MAT_DATE_LOCALE]},
        {provide: MAT_DATE_FORMATS, useValue: MY_FORMATS}
    ]
})
export class ModalEventosDocumentosComponent extends BaseComponentView implements OnInit{
    public parent         : any;
    public ofe_id         : any;
    public act_id         : number;
    public radian         : boolean = false;
    public cdo_ids        : any;
    public documentos     : any;
    public selectedOption : any;
    public textosDinamicos: any;
    public formErrors     : any;

    public form            : FormGroup;
    public campos_fnc      : FormArray;
    public motivo_rechazo  : AbstractControl;
    public concepto_rechazo: AbstractControl;

    // Radian
    public tipo_documento    : AbstractControl;
    public num_documento     : AbstractControl;
    public nombres           : AbstractControl;
    public apellidos         : AbstractControl;
    public cargo             : AbstractControl;
    public area_seccion      : AbstractControl;
    public observacion       : AbstractControl;
    public usuarios_notificar: AbstractControl;

    public listaDocumentos                         = '';
    public arrConceptosRechazo                     = [];
    public arrTiposDocumentos                      = [];
    public ofeRecepcionFncActivo                   = 'NO';
    public ofeRecepcionFncConfiguracion            : any;
    public estadoValidacionEnProcesoPendiente      : any;
    public arrCamposRecepcionFnc                   : Array<any> = [];
    public usuariosNotificarValidacion             : Array<any> = [];
    public maxDateFechaFin                         = new Date();

    public tablaDatosParametricosValidacion: string = 'pry_datos_parametricos_validacion';

    /**
     * Constructor de ModalEventosDocumentosComponent.
     * 
     * @param {*} data
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<ModalEventosDocumentosComponent>} modalRef
     * @param {BaseService} _baseService
     * @param {ParametrosService} _parametrosService
     * @param {DatosParametricosValidacionService} _datosParametricosService
     * @param {DocumentosRecibidosService} _documentosRecibidosService
     * @param {ValidacionDocumentosService} _validacionDocumentosService
     * @param {RadianService} _radianService
     * @memberof ModalEventosDocumentosComponent
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private formBuilder                 : FormBuilder,
        private modalRef                    : MatDialogRef<ModalEventosDocumentosComponent>,
        private _baseService                : BaseService,
        private _parametrosService          : ParametrosService,
        private _datosParametricosService   : DatosParametricosValidacionService,
        private _documentosRecibidosService : DocumentosRecibidosService,
        private _validacionDocumentosService: ValidacionDocumentosService,
        private _radianService              : RadianService
    ) {
        super();
        this.parent             = data.parent;
        this.documentos         = data.documentos.documentos_procesar;
        this.selectedOption     = data.selectedOption;
        this.ofe_id             = data.documentos.ofe_id;
        this.act_id             = data.documentos.act_id;
        this.arrTiposDocumentos = data.documentos.tipo_documentos;
        this.radian             = data.radian;
        this.cdo_ids            = data.documentos.cdo_ids;

        this.ofeRecepcionFncActivo              = data.ofeRecepcionFncActivo;
        this.ofeRecepcionFncConfiguracion       = data.ofeRecepcionFncConfiguracion;
        this.estadoValidacionEnProcesoPendiente = data.documentos.estado_validacion_en_proceso_pendiente;

        this.initForm();
        this.buildErrorsObjetc();
    }

    /**
     * ngOnInit de ModalEventosDocumentosComponent.
     *
     * @memberof ModalEventosDocumentosComponent
     */
    ngOnInit() {
        switch (this.selectedOption) {
            case 'acuse_recibo':
                this.textosDinamicos = 'Acuse de Recibo';
                break;

            case 'recibo_del_bien':
                this.textosDinamicos = 'Recibo del bien y/o prestación del servicio';
                break;

            case 'aceptacion_documento':
                this.textosDinamicos = 'Aceptación Expresa';
                break;

            case 'aceptacion_tacita':
                this.textosDinamicos = 'Aceptación Tácita';
                break;

            case 'rechazo_documento':
                this.loadConceptosRechazo();
                this.textosDinamicos = 'Reclamo (Rechazo)';
                break;

            case 'datos_validacion':
                this.textosDinamicos = 'Datos Validación';
                break;

            case 'enviar_a_validacion':
                this.textosDinamicos = 'Enviar a Validación';
                break;

            case 'validar':
                this.textosDinamicos = 'Validar';
                this.listaUsuariosNotificarValidacion();
                break;

            case 'rechazar':
                this.textosDinamicos = 'Rechazar';
                break;

            case 'pagar':
                this.textosDinamicos = 'Pagar';
                break;
        }
    }

    /**
     * Inicializando el formulario.
     * 
     * @memberof ModalEventosDocumentosComponent
     */
    private initForm(): void {
        if (this.selectedOption === 'rechazo_documento') {
            this.form = this.formBuilder.group({
                'motivo_rechazo': [''],
                'concepto_rechazo': ['', Validators.compose([Validators.required])]
            });
    
            this.motivo_rechazo = this.form.controls['motivo_rechazo'];
            this.concepto_rechazo = this.form.controls['concepto_rechazo'];
        } else if (!this.radian && (this.selectedOption === 'recibo_del_bien' || this.selectedOption === 'datos_validacion'  || this.selectedOption === 'enviar_a_validacion' || this.selectedOption === 'validar' || this.selectedOption === 'rechazar' || this.selectedOption === 'pagar')) {
            let controles: any;

            if(this.ofeRecepcionFncActivo == 'SI' && (
                ((this.selectedOption === 'recibo_del_bien' || this.selectedOption === 'datos_validacion' || this.selectedOption === 'enviar_a_validacion') && this.ofeRecepcionFncConfiguracion.evento_recibo_bien && this.ofeRecepcionFncConfiguracion.evento_recibo_bien.length > 0) ||
                (this.selectedOption === 'validar' && this.ofeRecepcionFncConfiguracion.validacion_aprobacion && this.ofeRecepcionFncConfiguracion.validacion_aprobacion.length > 0) ||
                (this.selectedOption === 'rechazar' && this.ofeRecepcionFncConfiguracion.validacion_rechazo && this.ofeRecepcionFncConfiguracion.validacion_rechazo.length > 0) ||
                (this.selectedOption === 'pagar' && this.ofeRecepcionFncConfiguracion.validacion_pagado && this.ofeRecepcionFncConfiguracion.validacion_pagado.length > 0)
            )) {
                if(this.selectedOption === 'validar' && this.ofeRecepcionFncConfiguracion.validacion_aprobacion && this.ofeRecepcionFncConfiguracion.validacion_aprobacion.length > 0)
                    controles = {
                        'campos_fnc'        : this.formBuilder.array([]),
                        'usuarios_notificar': [''],
                        'motivo_rechazo'    : ['']
                    }
                else
                    controles = {
                        'campos_fnc'        : this.formBuilder.array([]),
                        'motivo_rechazo'    : ['']
                    }
            } else {
                controles = {
                    'motivo_rechazo': ['']
                }
            }

            this.form               = this.formBuilder.group(controles);
            this.motivo_rechazo     = this.form.controls['motivo_rechazo'];
            this.usuarios_notificar = this.form.controls['usuarios_notificar'];

            if(this.ofeRecepcionFncActivo == 'SI' && 
                (
                    (this.ofeRecepcionFncConfiguracion.evento_recibo_bien && this.ofeRecepcionFncConfiguracion.evento_recibo_bien.length > 0) || 
                    (this.ofeRecepcionFncConfiguracion.validacion_aprobacion && this.ofeRecepcionFncConfiguracion.validacion_aprobacion.length > 0) || 
                    (this.ofeRecepcionFncConfiguracion.validacion_rechazo && this.ofeRecepcionFncConfiguracion.validacion_rechazo.length > 0) ||
                    (this.ofeRecepcionFncConfiguracion.validacion_pagado && this.ofeRecepcionFncConfiguracion.validacion_pagado.length > 0)
                )
            )
                this.agregarCamposRecepcionFnc();
        } else if (this.selectedOption === 'recibo_del_bien' && this.radian) {
            this.camposFormRadian();
        } else {
            let camposAdicionales: any;
            if (this.radian && this.selectedOption === 'acuse_recibo') {
                this.camposFormRadian();
            } else {
                camposAdicionales = {
                    'motivo_rechazo': ['']
                }
                this.form = this.formBuilder.group(camposAdicionales);
                this.motivo_rechazo = this.form.controls['motivo_rechazo'];
            }
        }
    }

    /**
     *Obtiene la lista de usuarios que pertenecen a la misma base de datos del usuario autenticado.
     *
     * @memberof ModalEventosDocumentosComponent
     */
    listaUsuariosNotificarValidacion() {
        this.loading(true);
        this._validacionDocumentosService.listaUsuariosNotificarValidacion().subscribe({
            next: ( res => {
                this.loading(false);
                this.usuariosNotificarValidacion = res.data.usuarios_notificar_validacion;
            }),
            error: ( error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar los usuarios a los cuales se puede notificar la validación');
            })
        });
    }

    /**
     * Agrega un FormGroup de campos de datos al formulario cuando aplica para Radian.
     *
     * @return {void} 
     * @memberof ModalEventosDocumentosComponent
     */
    camposFormRadian(): void {
        let camposAdicionales = {
            'observacion'   : [''],
            'tipo_documento': ['', Validators.compose([Validators.required])],
            'num_documento' : ['', Validators.compose([Validators.required, Validators.maxLength(20)])],
            'nombres'       : ['', Validators.compose([Validators.required, Validators.maxLength(100)])],
            'apellidos'     : ['', Validators.compose([Validators.required, Validators.maxLength(100)])],
            'cargo'         : ['', Validators.compose([Validators.maxLength(100)])],
            'area_seccion'  : ['', Validators.compose([Validators.maxLength(100)])]
        }

        this.form           = this.formBuilder.group(camposAdicionales);
        this.observacion    = this.form.controls['observacion'];
        this.tipo_documento = this.form.controls['tipo_documento'];
        this.num_documento  = this.form.controls['num_documento'];
        this.nombres        = this.form.controls['nombres'];
        this.apellidos      = this.form.controls['apellidos'];
        this.cargo          = this.form.controls['cargo'];
        this.area_seccion   = this.form.controls['area_seccion'];
    }

    /**
     * Obtiene los datos de los inputs del form de Radian.
     *
     * @return {Object} 
     * @memberof ModalEventosDocumentosComponent
     */
    valoresCamposFormRadian(): object {
        let objCamposAcuseRadian = {};
        objCamposAcuseRadian['observacion']     = this.form.controls['observacion'].value;
        objCamposAcuseRadian['tdo_id']          = this.form.controls['tipo_documento'].value;
        objCamposAcuseRadian['identificacion']  = this.form.controls['num_documento'].value;
        objCamposAcuseRadian['nombres']         = this.form.controls['nombres'].value;
        objCamposAcuseRadian['apellidos']       = this.form.controls['apellidos'].value;
        objCamposAcuseRadian['cargo']           = this.form.controls['cargo'].value;
        objCamposAcuseRadian['area']            = this.form.controls['area_seccion'].value;

        return objCamposAcuseRadian;
    }

    /**
     * Agrega nuevos campos de datos al formulario.
     *
     * @memberof ModalEventosDocumentosComponent
     */
    agregarCamposRecepcionFnc(): void {
        let arrayControls: FormGroup[] = [];
        arrayControls   = this.getControlsCamposRecepcionFnc();
        this.campos_fnc = this.form.get('campos_fnc') as FormArray;

        arrayControls.forEach(reg => {
            this.campos_fnc.push(reg);
        });
    }

    /**
     * Define la expresión regular para campo de tipo texto cuya configuración incluye las propiedades 'numerico' y 'permite_espacios'.
     *
     * Aplica dentro del proceso de Validación de FNC
     *
     * @private
     * @param {*} configCampo Objecto que contiene las propiedades de configuración del campo.
     * @return {string} 
     * @memberof ModalEventosDocumentosComponent
     */
    private definirExpresionParaTexto(configCampo: any): string {
        let expresionParaTexto: string;

        if(configCampo.numerico && configCampo.permite_espacios) {
            if (configCampo.exacta === 'SI') {
                if(configCampo.numerico === 'SI' && configCampo.permite_espacios === 'SI')
                    expresionParaTexto = "^[\\d\\s]{" + configCampo.longitud + "}$";
                else if(configCampo.numerico === 'SI' && configCampo.permite_espacios === 'NO')
                    expresionParaTexto = "^[\\d]{" + configCampo.longitud + "}$";
                else
                    expresionParaTexto = "^[\\s\\S]{" + configCampo.longitud + "}$";
            } else {
                if(configCampo.numerico === 'SI' && configCampo.permite_espacios === 'SI')
                    expresionParaTexto = "^[\\d\\s]{1," + configCampo.longitud + "}$";
                else if(configCampo.numerico === 'SI' && configCampo.permite_espacios === 'NO')
                    expresionParaTexto = "^[\\d]{1," + configCampo.longitud + "}$";
                else
                    expresionParaTexto = "^[\\s\\S]{1," + configCampo.longitud + "}$";
            }
        } else {
            if (configCampo.exacta === 'SI') {
                expresionParaTexto = "^[\\s\\S]{" + configCampo.longitud + "}$";
            } else {
                expresionParaTexto = "^[\\s\\S]{1," + configCampo.longitud + "}$";
            }
        }
        
        return expresionParaTexto;
    }

    /**
     * Obtiene la configuración de FNC para los campos personalizados de este OFE.
     *
     * @private
     * @return {*} 
     * @memberof ModalEventosDocumentosComponent
     */
    private definirRecepcionFncConfiguracion() {
        switch (this.selectedOption) {
            case 'recibo_del_bien':
            case 'datos_validacion':
            case 'enviar_a_validacion':
                return this.ofeRecepcionFncConfiguracion.evento_recibo_bien;
            case 'validar':
                return this.ofeRecepcionFncConfiguracion.validacion_aprobacion;
            case 'rechazar':
                return this.ofeRecepcionFncConfiguracion.validacion_rechazo;
            case 'pagar':
                return this.ofeRecepcionFncConfiguracion.validacion_pagado;
        }
    }

    /**
     * Agrega un FormGroup de campos de datos al formulario.
     *
     * @return {FormGroup} 
     * @memberof ModalEventosDocumentosComponent
     */
    getControlsCamposRecepcionFnc(): Array<FormGroup> {
        let formGroup: FormGroup;
        let objConfiguracion = this.definirRecepcionFncConfiguracion();

        const controlsCamposRecepcionFnc: FormGroup[] = objConfiguracion.map((configCampo: any, index: number) => {
            let nombreCampo = this._baseService.sanitizarString(configCampo.campo);
            let valorCampo: any;

            if((this.selectedOption === 'datos_validacion' || this.selectedOption === 'enviar_a_validacion' || this.selectedOption === 'recibo_del_bien') && this.estadoValidacionEnProcesoPendiente && this.estadoValidacionEnProcesoPendiente !== undefined) {
                let informacionAdicional = this.estadoValidacionEnProcesoPendiente.est_informacion_adicional ? JSON.parse(this.estadoValidacionEnProcesoPendiente.est_informacion_adicional) : '';

                if(informacionAdicional.campos_adicionales && informacionAdicional.campos_adicionales.length > 0)
                    valorCampo = informacionAdicional.campos_adicionales.find(campoAdicional => {
                        return campoAdicional.campo === nombreCampo
                    });

                if(valorCampo)
                    valorCampo = valorCampo.valor;
            }

            if(configCampo.tipo === 'texto') {
                let expresionParaTexto = this.definirExpresionParaTexto(configCampo);
                formGroup = this.formBuilder.group({
                    [nombreCampo]: [valorCampo, [Validators.pattern(new RegExp(expresionParaTexto))]]
                });
            } else if(configCampo.tipo === 'textarea') {
                if (configCampo.longitud !== '') {
                    if (configCampo.exacta === 'SI') {
                        let expresionParaTexto = "^[\\s\\S]{" + configCampo.longitud + "}$";
                        formGroup = this.formBuilder.group({
                            [nombreCampo]: [valorCampo, [Validators.pattern(new RegExp(expresionParaTexto))]]
                        });
                    } else {
                        let expresionParaTexto = "^[\\s\\S]{1," + configCampo.longitud + "}$";
                        formGroup = this.formBuilder.group({
                            [nombreCampo]: [valorCampo, [Validators.pattern(new RegExp(expresionParaTexto))]]
                        });
                    }
                } else {
                    formGroup = this.formBuilder.group({
                        [nombreCampo]: [valorCampo]
                    });
                }
            } else if (configCampo.tipo === 'por_defecto') {
                if (configCampo.longitud !== '') {
                    let expresionParaTexto = "^[\\s\\S]{1," + configCampo.longitud + "}$";
                    formGroup = this.formBuilder.group({
                        [nombreCampo]: [configCampo.opciones, [Validators.pattern(new RegExp(expresionParaTexto))]]
                    });
                } else {
                    formGroup = this.formBuilder.group({
                        [nombreCampo]: [valorCampo]
                    });
                }
            } else if (configCampo.tipo === 'numerico') {
                let validacionNumDecimal = configCampo.longitud.split('.');
                if (configCampo.exacta === 'SI') {
                    if (validacionNumDecimal[1] == '0') {
                        let expresionParaNumerico = "^\\d{" + validacionNumDecimal[0] + "}$";
                        formGroup = this.formBuilder.group({
                            [nombreCampo]: [valorCampo, [Validators.pattern(new RegExp(expresionParaNumerico))]]
                        });
                    } else {
                        let expresionParaNumerico = "^\\d{" + validacionNumDecimal[0] + "}(\\.\\d{" + validacionNumDecimal[1] + "})$";
                        formGroup = this.formBuilder.group({
                            [nombreCampo]: [valorCampo, [Validators.pattern(new RegExp(expresionParaNumerico))]]
                        });
                    }
                } else {
                    if (validacionNumDecimal[1] == '0') {
                        let expresionParaNumerico = "^\\d{1," + validacionNumDecimal[0] + "}$";
                        formGroup = this.formBuilder.group({
                            [nombreCampo]: [valorCampo, [Validators.pattern(new RegExp(expresionParaNumerico))]]
                        });
                    } else {
                        let expresionParaNumerico = "^\\d{1," + validacionNumDecimal[0] + "}(\\.\\d{1," + validacionNumDecimal[1] + "})?$";
                        formGroup = this.formBuilder.group({
                            [nombreCampo]: [valorCampo, [Validators.pattern(new RegExp(expresionParaNumerico))]]
                        });
                    }
                }
            } else if (configCampo.tipo === 'multiple') {
                formGroup = this.formBuilder.group({
                    [nombreCampo]: [valorCampo]
                });
            } else if (configCampo.tipo === 'parametrico' && configCampo.tabla && configCampo.tabla === this.tablaDatosParametricosValidacion) {
                formGroup = this.formBuilder.group({
                    [nombreCampo]: [valorCampo !== undefined ? valorCampo : 'NO APLICA']
                });

                this.obtenerListaDatosParametricosValidacion(index, configCampo.clasificacion);
            } else if (configCampo.tipo === 'date') {
                formGroup = this.formBuilder.group({
                    [nombreCampo]: [valorCampo, configCampo.obligatorio === 'SI' ? [Validators.required] : []]
                });
            }

            return formGroup;
        });

        return controlsCamposRecepcionFnc;
    }

    /**
     * Realiza la consulta de los datos paramétricos de validación y asigna los valores encontrados a la posición 'opciones' del objeto correspndiente.
     *
     * @private
     * @param {number} index Indica la posición del objeto en procesamiento dentro del array en ofeRecepcionFncConfiguracion
     * @param {string} clasificacion Clasificación que debe ser filtrada en la consulta
     * @memberof ModalEventosDocumentosComponent
     */
    private obtenerListaDatosParametricosValidacion(index: number, clasificacion: string) {
        this.loading(true);
        this._datosParametricosService.listarDatosParametricosValidacion(this.ofeRecepcionFncConfiguracion.evento_recibo_bien[index].campo, clasificacion).subscribe({
            next: ( res => {
                this.loading(false);
                this.ofeRecepcionFncConfiguracion.evento_recibo_bien[index].opciones = res.data.datos_parametricos_clasificacion;
            }),
            error: ( error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar los Datos Parametricos de Validación');
            })
        });
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     * @memberof ModalEventosDocumentosComponent
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            concepto_rechazo: {
                required: 'El concepto de rechazo es requerido!'
            },
            tdo_id: {
                required: 'El Tipo de Documento es requerido!',
            },
            num_documento: {
                required: 'El número de Documento es requerido!',
            },
            nombres: {
                required: 'Los Nombres son requeridos!',
            },
            apellidos: {
                required: 'Los Apellidos son requeridos!',
            } 
        };   
    }

    /**
     * Carga el listado de conceptos de rechazo.
     * 
     * @memberof ModalEventosDocumentosComponent
     */
    public loadConceptosRechazo() {
        this.loading(true);
        this._parametrosService.listarSelect('concepto-rechazo/listar-select').subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    this.arrConceptosRechazo = res.data.conceptos_rechazo;
                }
            },
            error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar los Conceptos de Rechazo');
            }
        );
    }

    /**
     * Cierra la ventana modal.
     *
     * @param {boolean} reload Indica si debe cerrar la modal
     * @memberof ModalEventosDocumentosComponent
     */
    public closeModal(reload:boolean) {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Procesa el rechazo de documentos.
     * 
     * @memberof ModalEventosDocumentosComponent
     */
    public procesamientoEventosDocumentos() {
        if (this.form.valid) {
            this.loading(true);
            if (this.selectedOption === 'rechazo_documento' && !this.radian) {
                this._documentosRecibidosService.agendarRechazoDocumentosRecibidos(this.ofe_id, this.cdo_ids, this.form.controls['motivo_rechazo'].value, this.form.controls['concepto_rechazo'].value).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', 'Rechazo de Documentos', 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar agendar el Rechazo de Documentos');
                    }
                );
            } else if (this.selectedOption === 'rechazo_documento' && this.radian){
                this._radianService.agendarRechazoDocumentosRadian(this.act_id, this.cdo_ids, this.form.controls['motivo_rechazo'].value, this.form.controls['concepto_rechazo'].value).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', 'Rechazo de Documentos', 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar agendar el Rechazo de Documentos');
                        this.closeModal(true);
                    }
                );
            }

            if (this.selectedOption === 'aceptacion_documento' && !this.radian) {
                this._documentosRecibidosService.agendarAceptacionExpresaDocumentosRecibidos(this.ofe_id, this.cdo_ids, this.form.controls['motivo_rechazo'].value).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', 'Aceptación Expresa', 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar agendar la Aceptación Expresa');
                    }
                );
            } else if(this.selectedOption === 'aceptacion_documento' && this.radian){
                this._radianService.agendarAceptacionExpresaDocumentosRadian(this.act_id, this.cdo_ids, this.form.controls['motivo_rechazo'].value).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', 'Aceptación Expresa', 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar la Aceptación Expresa');
                        this.closeModal(true);
                    }
                );
            }

            if (this.selectedOption === 'acuse_recibo' && !this.radian) {
                this._documentosRecibidosService.agendarAcuseReciboDocumentosRecibidos(this.ofe_id, this.cdo_ids, this.form.controls['motivo_rechazo'].value).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', 'Acuse Recibo', 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar agendar el Acuse Recibo');
                    }
                );
            } else if(this.selectedOption === 'acuse_recibo' && this.radian) {
                let objCamposAcuseRadian = this.valoresCamposFormRadian();

                this._radianService.agendarAcuseReciboDocumentosRadian(this.act_id, this.cdo_ids, objCamposAcuseRadian).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', 'Acuse Recibo', 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar agendar el Acuse Recibo');
                    }
                );
            }

            if (this.selectedOption === 'recibo_del_bien' && !this.radian) {
                let campos_fnc = null;
                if(this.ofeRecepcionFncActivo == 'SI' && this.ofeRecepcionFncConfiguracion.evento_recibo_bien && this.ofeRecepcionFncConfiguracion.evento_recibo_bien.length > 0) {
                    let arrCamposFnc = this.form.get('campos_fnc') as FormArray;
                    campos_fnc       = this.getFormValuesFnc(arrCamposFnc);
                }

                this._documentosRecibidosService.agendarReciboBienDocumentosRecibidos(this.ofe_id, this.cdo_ids, this.form.controls['motivo_rechazo'].value, campos_fnc).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', 'Recibo del Bien', 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar agendar el Recibo del Bien');
                    }
                );
            } else if (this.selectedOption === 'recibo_del_bien' && this.radian) {
                let objCamposAcuseRadian = this.valoresCamposFormRadian();

                this._radianService.agendarReciboBienDocumentosRadian(this.act_id, this.cdo_ids, objCamposAcuseRadian).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', 'Recibo del Bien', 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar agendar el Recibo del Bien');
                    }
                );
            }

            if(this.selectedOption === 'aceptacion_tacita' && this.radian){
                this._radianService.agendarAceptacionTacitaDocumentosRadian(this.act_id, this.cdo_ids, this.form.controls['motivo_rechazo'].value).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', 'Aceptación Tácita', 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar la Aceptación Tácita');
                        this.closeModal(true);
                    }
                );
            }

            if (this.selectedOption === 'datos_validacion' || this.selectedOption === 'enviar_a_validacion') {
                let campos_fnc     = null;
                let tituloMensajes = this.selectedOption == 'datos_validacion' ? 'Datos Validación' : 'Enviar a Validación';
                let estadoCrear    = this.selectedOption == 'datos_validacion' ? 'enproceso' : 'pendiente';

                if(this.ofeRecepcionFncActivo == 'SI' && this.ofeRecepcionFncConfiguracion.evento_recibo_bien && this.ofeRecepcionFncConfiguracion.evento_recibo_bien.length > 0) {
                    let arrCamposFnc = this.form.get('campos_fnc') as FormArray;
                    campos_fnc       = this.getFormValuesFnc(arrCamposFnc);
                }

                this._documentosRecibidosService.crearEstadoValidacionDocumentosRecibidos(this.ofe_id, this.cdo_ids, campos_fnc, estadoCrear).subscribe({
                    next: (response => {
                        this.loading(false);
                        this.showSuccess(response.message, 'success', tituloMensajes, 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    }),
                    error: (error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar guardar los Datos de Validación');
                    })
                });
            }

            if (this.selectedOption === 'validar' || this.selectedOption === 'rechazar' || this.selectedOption === 'pagar') {
                let campos_fnc = null;
                let accionTxt = capitalize.startCase(capitalize.toLower(this.selectedOption));
                if((this.selectedOption === 'validar' && this.ofeRecepcionFncActivo == 'SI' && this.ofeRecepcionFncConfiguracion.validacion_aprobacion && this.ofeRecepcionFncConfiguracion.validacion_aprobacion.length > 0) ||
                    (this.selectedOption === 'rechazar' && this.ofeRecepcionFncActivo == 'SI' && this.ofeRecepcionFncConfiguracion.validacion_rechazo && this.ofeRecepcionFncConfiguracion.validacion_rechazo.length > 0) ||
                    (this.selectedOption === 'pagar' && this.ofeRecepcionFncActivo == 'SI' && this.ofeRecepcionFncConfiguracion.validacion_pagado && this.ofeRecepcionFncConfiguracion.validacion_pagado.length > 0)) 
                {
                    let arrCamposFnc = this.form.get('campos_fnc') as FormArray;
                    campos_fnc       = this.getFormValuesFnc(arrCamposFnc);
                }

                let correosNotificar: string[];
                if(this.selectedOption === 'validar')
                    correosNotificar = this.form.controls['usuarios_notificar'].value;

                this._documentosRecibidosService.crearEstadoValidacionDocumentosRecibidos(this.ofe_id, this.cdo_ids, campos_fnc, this.selectedOption, correosNotificar).subscribe(
                    response => {
                        this.loading(false);

                        if(response.errors)
                            response.message += '<br><br>Observaciones:<br>' + response.errors.join(' || ');

                        this.showSuccess(response.message, 'success', accionTxt, 'Ok', 'btn btn-success');
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al intentar '+ accionTxt);
                    }
                );
            }
        }
    }

    /**
     * Arma el array de objetos que será enviado como parámetro en el request.
     * 
     * Aplica en el proceso de validación de documentos de FNC en Recepción
     *
     * @private
     * @param {FormArray} arrCamposFnc Form array que contiene los campos dinámicos que se han creado de acuerdo a la configuración del OFE
     * @return {Array<Object>} Array de objetos que será enviado como parámetro en el request
     * @memberof ModalEventosDocumentosComponent
     */
    private getFormValuesFnc (arrCamposFnc: FormArray): Array<Object> {
        let valoresFormFnc: Array<Object> = [];
        let objConfiguracion = this.definirRecepcionFncConfiguracion();

        objConfiguracion.map((configCampo: any, index: number) => {
            let nombreCampo = this._baseService.sanitizarString(configCampo.campo);

            if (configCampo.tipo === 'date') {
                valoresFormFnc.push({
                    [nombreCampo]: moment(arrCamposFnc.controls[index].get(nombreCampo).value).format('YYYY-MM-DD')
                });
            } else {
                valoresFormFnc.push({
                    [nombreCampo]: arrCamposFnc.controls[index].get(nombreCampo).value
                });
            }
        });

        return valoresFormFnc;
    }
}
