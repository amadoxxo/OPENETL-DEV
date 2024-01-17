import {Component, Input, OnInit, ViewChild} from '@angular/core';
import {AbstractControl, FormGroup, Validators} from '@angular/forms';

@Component({
    selector: 'app-contact-form-group-control',
    templateUrl: './contact-form-group-control.component.html',
    styleUrls: ['./contact-form-group-control.component.scss']
})

export class ContactFormGroupControlComponent implements OnInit {
    @ViewChild('Nombre', { static: true }) inputNombre;
    @ViewChild('Direccion', { static: true }) inputDireccion;
    @ViewChild('Telefono', { static: true }) inputTelefono;
    @ViewChild('Correo', { static: true }) inputCorreo;
    @ViewChild('Observaciones', { static: true }) inputObservaciones;

    @Input() titleGroup = '';
    @Input() formGroup: FormGroup;
    @Input() ver: boolean;

    public con_nombre: AbstractControl = null;
    public con_direccion: AbstractControl = null;
    public con_telefono: AbstractControl = null;
    public con_correo: AbstractControl = null;
    public con_observaciones: AbstractControl = null;
    public con_tipo: AbstractControl = null;
    private contactvalidation: boolean = false;

    /**
     * Constructor
     */
    constructor() {
    }

    /**
     * Inicializando los campos del formulario
     */
    ngOnInit(): void {
        this.con_correo = this.formGroup.controls['con_correo'];
        this.con_direccion = this.formGroup.controls['con_direccion'];
        this.con_nombre = this.formGroup.controls['con_nombre'];
        this.con_observaciones = this.formGroup.controls['con_observaciones'];
        this.con_telefono = this.formGroup.controls['con_telefono'];
        this.con_tipo = this.formGroup.controls['con_tipo'];
    }

    limpiarCamposGroup() {
        this.clearComponent(this.con_correo);
        this.clearComponent(this.con_direccion);
        this.clearComponent(this.con_nombre);
        this.clearComponent(this.con_observaciones);
        this.clearComponent(this.con_telefono);
    }

    clearComponent(control: AbstractControl) {
        control.setValue('');
        control.setErrors(null);
    }

    /**
     * Verifica si se debe de validar o no el grupo de contacto completo
     */
    changeContactoGroup() {
        let fields = ['con_nombre', 'con_direccion', 'con_telefono', 'con_correo', 'con_observaciones'];
        let strValidacion = false;
        this.contactvalidation = false;

        for (const field of fields) {
            if (this.formGroup.controls[field].value.length > 0 && strValidacion === false) {
                strValidacion = true;
                this.contactvalidation = true;
            }
        }

        if (strValidacion) {
            this.con_nombre.setValidators([Validators.required, Validators.maxLength(255)]);
            this.con_direccion.setValidators([Validators.required, Validators.maxLength(255)]);
            this.con_telefono.setValidators([Validators.required, Validators.maxLength(50)]);
            this.con_correo.setValidators([Validators.required, Validators.email]);
        } else {
            for (const field of fields) {
                this.formGroup.controls[field].clearValidators();
                this.formGroup.controls[field].markAsUntouched();
            }
        }
        for (const field of fields)
            this.formGroup.controls[field].updateValueAndValidity();
    }
}
