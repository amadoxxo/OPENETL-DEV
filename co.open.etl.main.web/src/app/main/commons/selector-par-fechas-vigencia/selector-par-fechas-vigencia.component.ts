import {Component, Input, OnInit} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {AbstractControl} from '@angular/forms';
import { MomentDateAdapter} from '@angular/material-moment-adapter';
import { DateAdapter, MAT_DATE_FORMATS, MAT_DATE_LOCALE } from '@angular/material/core';

export const MY_FORMATS = {
    parse: {
        dateInput: 'YYYY-MM-DD',
    },
    display: {
        dateInput: 'YYYY-MM-DD',
        monthYearLabel: 'YYYY MMM ',
        dateA11yLabel: 'LL',
        monthYearA11yLabel: 'YYYY MMMM',
    },
};


@Component({
    selector: 'app-selector-par-fechas-vigencia',
    templateUrl: './selector-par-fechas-vigencia.component.html',
    styleUrls: ['./selector-par-fechas-vigencia.component.scss'],
    providers: [
        {provide: MAT_DATE_LOCALE, useValue: 'es-ES'},
        {provide: DateAdapter, useClass: MomentDateAdapter, deps: [MAT_DATE_LOCALE]},
        {provide: MAT_DATE_FORMATS, useValue: MY_FORMATS},
    
      ]
})
export class SelectorParFechasVigenciaComponent extends BaseComponent implements OnInit {

    @Input() fecha_vigencia_desde: AbstractControl = null;
    @Input() fecha_vigencia_hasta: AbstractControl = null;
    @Input() hora_vigencia_desde: AbstractControl = null;
    @Input() hora_vigencia_hasta: AbstractControl = null;
    @Input() fecha_vigencia_desde_anterior: AbstractControl = null;
    @Input() fecha_vigencia_hasta_anterior: AbstractControl = null;

    // Mínimo de fechas
    public minDateFechaFin = null;

    constructor() {
        super();
    }

    ngOnInit() {
        if (this.fecha_vigencia_desde.value !== "" && this.fecha_vigencia_desde.value !== null) {
            this.minDateFechaFin = this.fecha_vigencia_desde.value;
        }
    }

    /**
    * Válida si se selecciona fecha vigencia hasta la fecha vigencia desde no puede ser menor a esta.
    *
    */
    setMinDateFechaFin (fechaIni) {
        if (fechaIni) {
            this.fecha_vigencia_hasta.setValue(null);
            this.minDateFechaFin = fechaIni;
        }
    }
}
