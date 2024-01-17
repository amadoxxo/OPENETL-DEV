import {Component, Input, OnInit} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {AbstractControl, FormBuilder} from '@angular/forms';
import {BusquedasPredictivasService} from '../../../services/commons/busquedas_predictivas.service';
import {concat, Observable, of, Subject} from 'rxjs';
import {Item} from '../../../services/commons/busquedas_predictivas.service';
import {catchError, debounceTime, distinctUntilChanged, switchMap, tap} from 'rxjs/operators';

@Component({
    selector: 'app-select-busqueda-predictiva',
    templateUrl: './select-busqueda-predictiva.component.html',
    styleUrls: ['./select-busqueda-predictiva.component.scss']
})
export class SelectBusquedaPredictivaComponent extends BaseComponent implements OnInit {

    @Input() esRequerido: boolean;
    @Input() label: string;
    @Input() parametrica: string;
    @Input() control: AbstractControl;
    @Input() id: string;
    @Input() descripcion: string;
    // public arrAcciones: Array<Object> = [];

    public valorEscogido: any;

    items$: Observable<Item[]>;
    itemsLoading: boolean = false;
    itemsInput$ = new Subject<string>();
    item_id: number = 0;

    constructor(
        private fb: FormBuilder,
        private _busquedasPredictivasService: BusquedasPredictivasService
        ) {
        super();
    }

    ngOnInit() {
        let vacioItem:Item[] = [];
        this.items$ = concat(
            of(vacioItem), // default items
            this.itemsInput$.pipe(
                debounceTime(750),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._busquedasPredictivasService.getItemsPredictivos(this.parametrica, term).pipe(
                    catchError(() => of(vacioItem)), // empty list on error
                    tap(() => this.loading(false))
                ))
            ));
    }

    /**
     * Limpiar item
     */
    public onClearItem() {
        this.item_id = 0;
    }

    /**
     * Seleccion de item
     * @param value
     */
    public itemByFn(value) {

    }
}
