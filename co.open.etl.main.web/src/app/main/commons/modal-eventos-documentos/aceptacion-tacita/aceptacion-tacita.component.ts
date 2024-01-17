import {Component, Input, OnInit} from '@angular/core';
import {AbstractControl} from '@angular/forms';

@Component({
  selector: 'app-aceptacion-tacita',
  templateUrl: './aceptacion-tacita.component.html',
  styleUrls: ['./aceptacion-tacita.component.scss']
})
export class AceptacionTacitaComponent implements OnInit {

    @Input() tiemposAceptacionTacita: Array<any> = [];
    @Input() tat_id: AbstractControl = null;
    @Input() ver: boolean;

    constructor() { }

    ngOnInit() {
      if(this.ver){
        this.tat_id.disable();
      }
    }
}
