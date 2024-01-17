import {Component, OnInit} from '@angular/core';

@Component({
  selector: 'app-cartera-vencida',
  templateUrl: './cartera-vencida.component.html',
  styleUrls: ['./cartera-vencida.component.scss']
})
export class CarteraVencidaComponent implements OnInit {
    public carteraVencidaMensaje: string = '';

    constructor() {}

    ngOnInit() {
        if(localStorage.getItem('cartera_vencida_mensaje') !== null && localStorage.getItem('cartera_vencida_mensaje') !== undefined)
            this.carteraVencidaMensaje = window.atob(localStorage.getItem('cartera_vencida_mensaje'));
    }
}
