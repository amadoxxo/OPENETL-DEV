import {Component, Input} from '@angular/core';
import {AbstractControl, Validators} from '@angular/forms';
import {BaseComponent} from '../../core/base_component';

@Component({
    selector: 'app-informacion-adicional',
    templateUrl: './informacion-adicional.component.html',
    styleUrls: ['./informacion-adicional.component.scss']
})
export class InformacionAdicionalComponent extends BaseComponent {

    @Input() nombreContacto: AbstractControl = null;
    @Input() telefono: AbstractControl = null;
    @Input() fax: AbstractControl = null;
    @Input() correo: AbstractControl = null;
    @Input() matricula: AbstractControl = null;
    @Input() actividadEconomica: AbstractControl = null;
    @Input() notas: AbstractControl = null;
    @Input() ver: boolean;
    @Input() editar?: boolean;
    @Input() tipo: string;
    @Input() longitudTelefonoFax: number;

    public validators = [Validators.pattern("^[0-9]*$")];
    public anchoFelx: number;
    public fraseActividadesEconomicas: string;

    /**
     *
     */
    constructor() {
        super();
    }

    ngOnInit(): void {
        if(this.tipo === 'OFE'){
            this.anchoFelx = 23;
            if((this.ver || this.editar) && !this.actividadEconomica.value)
                this.fraseActividadesEconomicas = 'Ingrese las actividades económicas';
            else
                this.fraseActividadesEconomicas = '';
        }else{
            this.anchoFelx = 32;
        }
    }
}
