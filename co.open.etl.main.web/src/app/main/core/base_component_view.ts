import {BaseComponent} from './base_component';
import {FormGroup} from '@angular/forms';

export class BaseComponentView extends BaseComponent {

    public form: FormGroup;
    public formErrors: any;
    public mode: number;

    /**
     * Constructor
     */
    constructor() {
        super();
    }

    public hasError = (controlName: string, errorName: string) => {
        return this.form.controls[controlName].hasError(errorName);
    };

    /**
     * Retorna un formulario de contacto
     */
    buildFormularioContacto(_formBuilder, con_tipo) {
        let group: FormGroup = _formBuilder.group({
            con_nombre: [''],
            con_direccion: [''],
            con_telefono: [''],
            con_correo: [''],
            con_observaciones: [''],
            con_tipo: ['']
        });
        group.controls['con_tipo'].setValue(con_tipo);
        return group;
    }
}