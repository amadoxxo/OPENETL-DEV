import {Component, Input, OnInit} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {AbstractControl, Validators} from '@angular/forms';

@Component({
    selector: 'app-notificaciones',
    templateUrl: './notificaciones.component.html',
    styleUrls: ['./notificaciones.component.scss']
})
export class NotificacionesComponent extends BaseComponent implements OnInit {

    @Input() correos: AbstractControl = null;
    @Input() tipo: string;
    @Input() ver: boolean;
    @Input() etiqueta: boolean;
    public validators = [Validators.pattern(new RegExp(/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.([a-zA-Z]{2,4})+$/))];

    /**
     * Constructor
     */
    constructor() {
        super();
    }

    ngOnInit() {}

}
