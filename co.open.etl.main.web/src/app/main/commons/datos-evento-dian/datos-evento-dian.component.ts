import {Component, Input, OnInit} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {FormBuilder, FormGroup, AbstractControl, Validators} from '@angular/forms';

@Component({
    selector: 'app-datos-evento-dian',
    templateUrl: './datos-evento-dian.component.html',
    styleUrls: ['./datos-evento-dian.component.scss']
})
export class DatosEventoDianComponent extends BaseComponent implements OnInit {

    @Input() tiposDocumento : Array<any>;
    @Input() ver            : boolean;
    @Input() tituloSeccion  : string;
    @Input() mostrarSeccion : boolean = true;

    // Variables del formulario
    public infoEventosDian    : FormGroup;
    public tdo_id             : AbstractControl;
    public use_identificacion : AbstractControl;
    public use_nombres        : AbstractControl;
    public use_apellidos      : AbstractControl;
    public use_cargo          : AbstractControl;
    public use_area           : AbstractControl;
    public formErrors         : any;

    // Setter para los tipos de documento
    set setTiposDocumento(value:any) {
        let existe = false;
        this.tiposDocumento.forEach(reg => {
            if (reg.tdo_codigo == value.tdo_codigo) {
                existe = true;
            }
        });
        if (!existe) {
            this.tiposDocumento.push(value);
        }
    }

    /**
     * Crea una instancia de DatosEventoDianComponent.
     * 
     * @param {FormBuilder} _formBuilder
     * @memberof DatosEventoDianComponent
     */
    constructor(private _formBuilder: FormBuilder) {
        super();
        this.buildFormularioInfoEventosDian();
        this.buildErrorsObject();
    }

    /**
     * ngOnInit de DatosEventoDianComponent.
     *
     * @memberof DatosEventoDianComponent
     */
    ngOnInit(){
        if(this.ver) {
            this.tdo_id.disable();
            this.use_identificacion.disable();
            this.use_nombres.disable();
            this.use_apellidos.disable();
            this.use_cargo.disable();
            this.use_area.disable();
        }
    }

    /**
     * Construcción de los datos del selector de datos generales.
     *
     * @return {*} 
     * @memberof DatosEventoDianComponent
     */
    buildFormularioInfoEventosDian() {
        this.infoEventosDian = this._formBuilder.group({
            tdo_id            : this.requerido(),
            use_identificacion: this.requerido(),
            use_nombres       : this.requeridoMaxlong(100),
            use_apellidos     : this.requeridoMaxlong(100),
            use_cargo         : [''],
            use_area          : ['']
        });

        this.tdo_id             = this.infoEventosDian.controls['tdo_id'];
        this.use_identificacion = this.infoEventosDian.controls['use_identificacion'];
        this.use_nombres        = this.infoEventosDian.controls['use_nombres'];
        this.use_apellidos      = this.infoEventosDian.controls['use_apellidos'];
        this.use_cargo          = this.infoEventosDian.controls['use_cargo'];
        this.use_area           = this.infoEventosDian.controls['use_area'];

        return this.infoEventosDian;
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     *
     * @memberof DatosEventoDianComponent
     */
    public buildErrorsObject() {
        this.formErrors = {
            tdo_id: {
                required: 'El Tipo de Documento es requerido!',
            },
            use_identificacion: {
                required: 'El número de Documento es requerido!',
            },
            use_nombres: {
                required: 'Los Nombres son requeridos!',
            },
            use_apellidos: {
                required: 'Los Apellidos son requeridos!',
            }
        };
    }

    /**
     * Setea la información de los campos del formulario.
     *
     * @param {*} data Información para asignar en el formulario
     * @memberof DatosEventoDianComponent
     */
    public setDataFormulario(data) {
        if (data.get_tipo_documento) {
            this.tdo_id.setValue(data.get_tipo_documento.tdo_codigo);
            let tipoDocumento = data.get_tipo_documento;
            tipoDocumento.tdo_codigo_descripion = data.get_tipo_documento.tdo_codigo + ' - ' + data.get_tipo_documento.tdo_descripcion;
            this.setTiposDocumento = tipoDocumento;
        } else {
            this.tdo_id.setValue(data.tdo_id);
        }

        this.use_identificacion.setValue(data.use_identificacion);
        this.use_nombres.setValue(data.use_nombres);
        this.use_apellidos.setValue(data.use_apellidos);
        this.use_cargo.setValue(data.use_cargo);
        this.use_area.setValue(data.use_area);
    }

    /**
     * Permite hacer una búsqueda del registro en tipos de documento
     *
     * @param {string} term Termino a buscar
     * @param {*} item Item
     * @return {*} 
     * @memberof DatosEventoDianComponent
     */
    customSearchFnTdo(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.tdo_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.tdo_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Evento que cambia la validación de los campos a requeridos o no requeridos.
     *
     * @param {string} value SI|NO Indica si deben ser requeridos
     * @memberof DatosEventoDianComponent
     */
    changeCamposRequeridos(value: string) {
        if(value === 'SI') {
            this.tdo_id.setValidators([Validators.required]);
            this.use_identificacion.setValidators([Validators.required]);
            this.use_nombres.setValidators([Validators.required, Validators.maxLength(100)]);
            this.use_apellidos.setValidators([Validators.required, Validators.maxLength(100)]);
        } else {
            this.tdo_id.reset();
            this.use_identificacion.reset();
            this.use_nombres.reset();
            this.use_apellidos.reset();
            this.use_cargo.reset();
            this.use_area.reset();
            this.tdo_id.clearValidators();
            this.use_identificacion.clearValidators();
            this.use_nombres.clearValidators();
            this.use_apellidos.clearValidators();
            this.use_cargo.clearValidators();
            this.use_area.clearValidators();
        }
        this.tdo_id.updateValueAndValidity();
        this.use_identificacion.updateValueAndValidity();
        this.use_nombres.updateValueAndValidity();
        this.use_apellidos.updateValueAndValidity();
        this.use_cargo.updateValueAndValidity();
        this.use_area.updateValueAndValidity();
    }
}
