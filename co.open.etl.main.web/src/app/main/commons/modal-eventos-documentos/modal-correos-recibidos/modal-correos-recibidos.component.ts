import {Component, Inject} from '@angular/core';
import {BaseComponentView} from '../../core/base_component_view';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import * as moment from 'moment';

@Component({
    selector: 'app-modal-correos-recibidos',
    templateUrl: './modal-correos-recibidos.component.html',
    styleUrls: ['./modal-correos-recibidos.component.scss']
})
export class ModalCorreosRecibidosComponent extends BaseComponentView {

    parent           : any;
    registro         : any;
    titulo           : string  = '';
    epmObservaciones : any = [];

    fechas : string = '';
    correos: string = '';

    moment = moment;

    /**
     * Constructor
     *
     * @param data
     * @param modalRef
     */
    constructor(@Inject(MAT_DIALOG_DATA) data,
                private modalRef: MatDialogRef<ModalCorreosRecibidosComponent>) {
        super();
        this.registro = data.item;
        this.epmObservaciones = JSON.parse(data.item.epm_observaciones);
        this.parent   = data.parent;
    }

    /**
     * Cierra la ventana modal de Códigos de Postal.
     *
     * @memberof ModalCorreosRecibidosComponent
     */
    public closeModal(reload): void {
        this.modalRef.close();
    }

    /**
     * Formatear el cuerpo del correo a HTML.
     *
     * @param {string} texto
     * @return {*} 
     * @memberof ModalCorreosRecibidosComponent
     */
    formatearTexto(texto: string) {
        texto = texto.split("\n").join("<br />");
        texto = texto.split("\r").join("<br />");

        return texto;
    }
}
