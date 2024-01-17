import {Component, Input, OnDestroy, OnInit} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {concat, Observable, of, Subject} from 'rxjs';
import {ProveedorTecnologico} from '../../models/proveedor-tecnologico.model';
import {catchError, debounceTime, distinctUntilChanged, filter, switchMap, tap} from 'rxjs/operators';
import {ProveedorTecnologicoService} from '../../../services/configuracion/proveedor-tecnologico.service';
import {AbstractControl} from '@angular/forms';

@Component({
    selector: 'app-selector-sft',
    templateUrl: './selector-sft.component.html',
    styleUrls: ['./selector-sft.component.scss']
})
export class SelectorSftComponent extends BaseComponent implements OnInit, OnDestroy {
    @Input() sft_id: AbstractControl = null;
    @Input() ver   : boolean;
    @Input() tipo? : string;

    proveedores$      : Observable<ProveedorTecnologico[]>;
    proveedoresInput$ = new Subject<string>();
    proveedoresLoading: boolean = false;
    selectedSftId     : any;
    placeholder       : any;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    constructor(private _sftServices: ProveedorTecnologicoService) {
        super();
    }

    ngOnInit() {
        switch(this.tipo) {
            default:
            case 'DN':
                this.placeholder = 'Software Proveedor Tecnológico: ';
                break;
            case 'DE':
                this.placeholder = 'Software Proveedor Tecnológico Emisión: ';
                break;
            case 'DS':
                this.placeholder = 'Software Proveedor Tecnológico Documento Soporte: ';
                break;
        }

        let aplicaPara = this.tipo ? this.tipo : 'DE';
        const vacioOfes: ProveedorTecnologico[] = [];
        this.proveedores$ = concat(
            of(vacioOfes),
            this.proveedoresInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._sftServices.searchSftNgSelect(term, aplicaPara).pipe(
                    catchError(() => of(vacioOfes)),
                    tap(() => this.loading(false))
                ))
            ));

        if(this.ver){
            this.sft_id.disable();
        }
    }

    /**
     * On destroy
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
    }
}
