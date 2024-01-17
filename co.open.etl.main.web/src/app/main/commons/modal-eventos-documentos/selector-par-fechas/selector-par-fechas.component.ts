import {Component, Input, OnInit} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {AbstractControl} from '@angular/forms';
import * as moment from 'moment';

@Component({
    selector: 'app-selector-par-fechas',
    templateUrl: './selector-par-fechas.component.html',
    styleUrls: ['./selector-par-fechas.component.scss']
})
export class SelectorParFechasComponent extends BaseComponent implements OnInit {

    @Input() fecha_desde: AbstractControl = null;
    @Input() fecha_hasta: AbstractControl = null;
    @Input() labelDesde : string;
    @Input() labelHasta : string;
    @Input() req        : boolean;
    @Input() timeLimit? : number = 12;

    // Mínimo de fechas
    public maxDate         = new Date();
    public minDateFechaFin = new Date();
    public maxDateFechaFin = new Date();

    constructor() {
        super();
    }

    ngOnInit() {
    }

    /**
    * Establece los límites de la fecha del select de Fecha Hasta basado en la fecha seleccionada en Fecha Desde.
    *
    */
    setMinDateFechaFin (fechaIni) {
        if(fechaIni){
            this.fecha_hasta.setValue(null);
            this.minDateFechaFin = fechaIni;
            let fechaActual = new Date();
            if (fechaActual.getFullYear() === fechaIni.year()) {
                if(this.timeLimit == 12)
                    this.maxDateFechaFin = fechaActual;
                else {
                    this.calcularFechasLimite();
                }
            } else {
                this.calcularFechasLimite();
            }
        }
    }

    /**
     * Calcula la fecha máxima límite cuando la propiedad timLimit es diferente de 12 (valor por defecto), o cuando el año de la fecha inicial es diferente del año actual.
     * 
     * @return {void}
     */
    calcularFechasLimite() {
        let fechaActual = new Date();
        let fechaFin    = moment(this.minDateFechaFin).add(this.timeLimit, 'months').toDate();

        if(fechaFin > fechaActual){
            this.maxDateFechaFin = fechaActual;
        } else {
            this.maxDateFechaFin = fechaFin;
        }
    }
}
