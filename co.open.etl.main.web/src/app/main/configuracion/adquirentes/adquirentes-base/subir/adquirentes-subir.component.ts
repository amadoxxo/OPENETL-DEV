import {Component, Input, OnInit} from '@angular/core';
import {BaseComponent} from 'app/main/core/base_component';

@Component({
    selector: 'app-adquirentes-subir',
    templateUrl: './adquirentes-subir.component.html',
    styleUrls: ['./adquirentes-subir.component.scss']
})
export class AdquirentesSubirComponent extends BaseComponent implements OnInit{

    @Input() tipoAdq: string;

    public titulo: string;
    public tipoCargaMasiva: string;
    /**
     * Constructor.
     * 
     */
    constructor(
    ) {
        super();
    }

    ngOnInit() {
        if (this.tipoAdq) {
            this.titulo = this.capitalize(this.tipoAdq);
            switch (this.tipoAdq) {
                case 'adquirentes':
                    this.tipoCargaMasiva = 'ADQ'
                    break;
                case 'autorizados':
                    this.tipoCargaMasiva = 'AUT'
                    break;
                case 'responsables':
                    this.tipoCargaMasiva = 'RES'
                    break;
                case 'vendedores':
                    this.tipoCargaMasiva = 'VEN'
                    break;
                default:
                    break;
            }
        }    
    }

    capitalize(string){
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
}
