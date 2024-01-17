import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { AbstractControl, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { BaseComponentView } from 'app/main/core/base_component_view';
import { JwtHelperService } from '@auth0/angular-jwt';
import { debounceTime, finalize, switchMap, tap, distinctUntilChanged, filter } from 'rxjs/operators';
import { ParametrosService } from '../../../../services/parametros/parametros.service';
import { OferentesService } from 'app/services/configuracion/oferentes.service';

@Component({
  selector: 'valores-por-defecto-documento-electronico-gestionar',
  templateUrl: './valores-por-defecto-documento-electronico-gestionar.component.html',
  styleUrls: ['./valores-por-defecto-documento-electronico-gestionar.component.scss']
})
export class ValoresPorDefectoDocumentoElectronicoGestionarComponent extends BaseComponentView implements OnInit {

    public ofe_id         : any;
    public usuario        : any;
    public form           : FormGroup;
    public action         : string;
    public parent         : any;
    public formErrors     : any;
    public valorPorDefecto: any;
    public isLoading      : boolean = false;
    public required       : boolean = true;

    public noCoincidences        : boolean;
    public arrItems              : any = [];
    public resultadosAutocomplete: any = [];

    public valor      : AbstractControl;
    public descripcion: AbstractControl;
    public editable   : AbstractControl;

    /**
     * Constructor
     * @param formBuilder
     * @param modalRef
     * @param data
     * @param _parametrosService
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private formBuilder       : FormBuilder,
        private modalRef          : MatDialogRef<ValoresPorDefectoDocumentoElectronicoGestionarComponent>,
        private _parametrosService: ParametrosService,
        private jwtHelperService  : JwtHelperService,
        private _oferentesService : OferentesService,
    ) {
        super();
        this.parent          = data.parent;
        this.action          = data.action;
        this.valorPorDefecto = data.valorPorDefecto;
        this.ofe_id          = data.ofe_id;
        this.usuario         = this.jwtHelperService.decodeToken();

        this.initForm();
        this.buildErrorsObjetc();
    }

    ngOnInit() {
        if (this.action === 'view'){
            this.disableFormControl(this.valor, this.editable, this.descripcion);
        }

        if (this.action === 'edit' && this.valorPorDefecto.tabla)
            this.valueChangesDescripcion();
    }

    /**
     * Inicializando el formulario.
     * 
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'valor'      : [''],
            'editable'   : [''],
            'descripcion': ['']
        }, {});

        this.valor       = this.form.controls['valor'];
        this.editable    = this.form.controls['editable'];
        this.descripcion = this.form.controls['descripcion'];

        this.editable.setValue(this.valorPorDefecto.editable ? this.valorPorDefecto.editable : '');

        if(this.valorPorDefecto.editable)
            this.editable.setValidators([Validators.required]);

        this.validaInputField(this.valorPorDefecto.editable);

        if(!this.valorPorDefecto.editable) {
            this.valor.setValidators([Validators.required]);
            this.valor.updateValueAndValidity({emitEvent: false});
        }

        if(this.valorPorDefecto.tabla && this.valorPorDefecto.tipo === 'PARAMETRO') {
            if(this.valorPorDefecto.valor) {
                this.valor.setValue(this.valorPorDefecto.valor, {emitEvent: false});
                if(this.valorPorDefecto.tabla !== 'etl_resoluciones_facturacion' && this.valorPorDefecto.tabla !== 'etl_forma_generacion_transmision')
                    this.descripcion.setValue(this.valorPorDefecto.valor + ' - ' + this.valorPorDefecto.valor_descripcion, {emitEvent: false});
                else if(this.valorPorDefecto.tabla === 'etl_forma_generacion_transmision')
                    this.descripcion.setValue(this.valorPorDefecto.valor_descripcion, {emitEvent: false});
                else
                    this.descripcion.setValue(this.valorPorDefecto.valor_descripcion, {emitEvent: false});
            }
        } else if(this.valorPorDefecto.opciones) {
            this.arrItems = this.valorPorDefecto.opciones;
            this.valor.setValue(this.valorPorDefecto.valor ? this.valorPorDefecto.valor : '');
        } else if(!this.valorPorDefecto.opciones && !this.valorPorDefecto.tabla) {
            this.valor.setValue(this.valorPorDefecto.valor ? this.valorPorDefecto.valor : '');
        }
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            editable: {
                required: 'Debe indicar si puede ser editable!'
            },
            descripcion: {
                required: 'La descripción es requerida!'
            },
            valor: {
                required: 'El valor es requerido!'
            }
        };   
    }

    /**
     * Cierra la ventana modal.
     * 
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.loadOfe();
    }

    /**
     * Actualiza el valor por defecto que se encuentra en edición.
     * 
     * @param values
     */
    saveItem(values) {
        let valorPorDefectoModificar = this.parent.datosFacturacionWeb.find(datos => datos.id === this.valorPorDefecto.id);

        valorPorDefectoModificar.editable = values.editable;
        if(valorPorDefectoModificar.tabla) {
            if(!this.descripcion.value) {
                valorPorDefectoModificar.valor = '';
                valorPorDefectoModificar.valor_descripcion = '';
            } else {
                valorPorDefectoModificar.valor = values.valor;
                if(valorPorDefectoModificar.tipo === 'PARAMETRO') {
                    valorPorDefectoModificar.valor_descripcion = values.descripcion.replace(values.valor + ' - ', '');
                }
            }
        } else {
            valorPorDefectoModificar.valor = values.valor;
            valorPorDefectoModificar.valor_descripcion = '';
        }

        let datosFacturacionWebEnviar = [];
        this.parent.datosFacturacionWeb.forEach(dato => {
            const {valor_descripcion, ...datoModificado} = dato;
            datosFacturacionWebEnviar.push(datoModificado);
        });

        const payload = {
            ofe_identificacion: this.parent._ofe_identificacion,
            ofe_datos_documentos_manuales: JSON.stringify(datosFacturacionWebEnviar)
        }

        this.loading(true);
        if (this.form.valid) {
            if (this.action === 'edit') {
                this._oferentesService.updateDatosFacturacionWeb(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.closeModal(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Valor por defecto actualizado', 'Ok', 'btn btn-success');
                    },
                    error => {
                        this.loading(false);
                        const texto_errores = this.parseError(error);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el valor por defecto', 'Ok', 'btn btn-danger');
                    });
            }
        }
    }

    /**
     * Verifica cambios sobre el formControl "descripcion" cuando funciona como autocomplete.
     *
     * @memberof ValoresPorDefectoDocumentoElectronicoGestionarComponent
     */
    valueChangesDescripcion(){
        this.form
        .get('descripcion')
        .valueChanges
        .pipe(
            filter(value => value.length >= 1),
            debounceTime(1000),
            distinctUntilChanged(),
            tap(() => {
                this.loading(true);
                this.form.get('descripcion').disable();
            }),
            switchMap(value =>
                this._parametrosService.searchParametricas(this.valorPorDefecto.tabla, this.valorPorDefecto.campo, value, this.ofe_id, this.valorPorDefecto.descripcion)
                    .pipe(
                        finalize(() => {
                            this.loading(false);
                            this.form.get('descripcion').enable();
                        })
                    )
            )
        )
        .subscribe(
            res => {
                this.resultadosAutocomplete = res.data;
                if (this.resultadosAutocomplete.length <= 0) {
                    this.valor.setValue('');
                    this.descripcion.setValue('');
                    this.noCoincidences = true;
                } else {
                    this.noCoincidences = false;
                }    
            },
            error => {
                this.loading(false);
                this.closeModal(false);
                this.mostrarErrores(error, 'Error al consultar');
            }
        );    
    }

    /**
     * Establece los valores para valor y descripcion cuando un item es seleccionado en un autocomplete
     *
     * @param object registroSeleccionado
     * @memberof ValoresPorDefectoDocumentoElectronicoGestionarComponent
     */
    setValor(registroSeleccionado){
        if(this.valorPorDefecto.tabla !== 'etl_resoluciones_facturacion' && this.valorPorDefecto.tabla !== 'etl_forma_generacion_transmision') {
            this.valor.setValue(registroSeleccionado.codigo, {emitEvent:false});
            this.descripcion.setValue(registroSeleccionado.codigo + ' - ' + registroSeleccionado.descripcion, {emitEvent:false});
        } else if(this.valorPorDefecto.tabla === 'etl_forma_generacion_transmision') {
            this.valor.setValue(registroSeleccionado.fgt_id, {emitEvent:false});
            let codigo = registroSeleccionado.fgt_codigo ? registroSeleccionado.fgt_codigo + ' - ' : '';
            this.descripcion.setValue(codigo + registroSeleccionado.fgt_descripcion, {emitEvent:false});
        } else {
            this.valor.setValue(registroSeleccionado.rfa_id, {emitEvent:false});
            let prefijo = registroSeleccionado.rfa_prefijo ? registroSeleccionado.rfa_prefijo + ' - ' : '';
            this.descripcion.setValue(prefijo + registroSeleccionado.rfa_resolucion, {emitEvent:false});
        }
    }

    /**
     * Establece obligatoriedad sobre al campo valor cuando la paramétrica es editable
     *
     * @param {string} valor
     * @memberof DatosFijosGestionarComponent
     */
    validaInputField(valor) {
        if(this.valorPorDefecto.tipo === 'PARAMETRO') {
            if(valor === 'SI') {
                this.required = false;
                this.valor.clearValidators();
                this.descripcion.clearValidators();
            } else if(valor === 'NO') {
                this.required = true;
                this.valor.setValidators([Validators.required]);
                this.descripcion.setValidators([Validators.required]);
            }
        } else {
            this.descripcion.clearValidators();
            if(valor === 'SI') {
                this.required = false;
                this.valor.clearValidators();

                if(this.valorPorDefecto.descripcion === 'Cantidad')
                    this.valor.setValidators([Validators.pattern("^[0-9]{1,15}\.?[0-9]{0,2}$")]);
                else if(this.valorPorDefecto.descripcion === 'Representación Gráfica')
                    this.valor.setValidators([Validators.pattern("^[0-9]{1,15}$")]);
            } else if(valor === 'NO') {
                this.required = true;

                if(this.valorPorDefecto.descripcion === 'Cantidad')
                    this.valor.setValidators([Validators.required, Validators.pattern("^[0-9]{1,15}\.?[0-9]{0,2}$")]);
                else if(this.valorPorDefecto.descripcion === 'Representación Gráfica')
                    this.valor.setValidators([Validators.required, Validators.pattern("^[0-9]{1,15}$")]);
                else
                    this.valor.setValidators([Validators.required]);
            }
        }

        this.valor.updateValueAndValidity({emitEvent: false});
        this.descripcion.updateValueAndValidity({emitEvent: false});
    }
}
