import {Component, Input, Output, OnInit, EventEmitter} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {AbstractControl} from '@angular/forms';

@Component({
    selector: 'app-selector-ofe-precargado',
    templateUrl: './selector-ofe-precargado.component.html',
    styleUrls: ['./selector-ofe-precargado.component.scss']
})
export class SelectorOfePrecargadoComponent extends BaseComponent implements OnInit {

    @Input() ofe_id: AbstractControl = null;
    @Input() oferentes: Array<any> = [];
    @Input() ver: boolean;
    @Input() label: string = 'Emisor';

    @Output() ofeSeleccionado = new EventEmitter();

    selectedOfeId: any;

    constructor(private _oferentesServices: OferentesService) {
        super();
    }

    ngOnInit() {
        if(this.ver){
            this.ofe_id.disable();
        }
    }

    onOfeSeleccionado(ofe) {
        if(ofe)
            this.ofeSeleccionado.emit(ofe.ofe_identificacion);
      }

    customSearchFnOfe(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.ofe_identificacion.toLocaleLowerCase().indexOf(term) > -1 || item.ofe_razon_social.toLocaleLowerCase().indexOf(term) > -1;
    }
}
