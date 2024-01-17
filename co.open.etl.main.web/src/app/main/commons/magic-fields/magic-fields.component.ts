import {Component, Input} from '@angular/core';

@Component({
    selector: 'app-magic-fields',
    templateUrl: './magic-fields.component.html',
    styleUrls: ['./magic-fields.component.scss']
})

export class MagicFieldsComponent {

    /**
     * Objeto a gestionar.
     * 
     */
    @Input() object: any = null;

    /**
     * Usuario que creo el registro.
     * 
     */
    @Input() usuario: any = null;

    /**
     * Indica si el componente en el cual se va a insertar es una modal.
     * 
     */
    @Input() modal: boolean;

    /**
     * Constructor
     */
    constructor() {
    }

    /**
     * Fecha de creación del registro.
     * 
     */
    get fechaCreacion () {
        return this.object ? this.object.fecha_creacion : '';
    }

    /**
     * Fecha de modificación del registro.
     * 
     */
    get fechaModificacion () {
        return this.object ? this.object.fecha_modificacion : '';
    }

    /**
     * Estado del registro.
     * 
     */
    get estado () {
        return this.object ? this.object.estado : '';
    }

    /**
     * Estado del registro.
     * 
     */
    get usuarioCreacion () {
        return this.usuario ? this.usuario.identificacion + ' - ' + this.usuario.nombre : '';
    }
}
