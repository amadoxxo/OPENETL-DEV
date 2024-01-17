import {Component, Input, OnDestroy, OnInit, ChangeDetectorRef} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {concat, Observable, of, Subject} from 'rxjs';
import {Oferente} from '../../models/oferente.model';
import {catchError, debounceTime, distinctUntilChanged, filter, switchMap, tap} from 'rxjs/operators';
import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {AbstractControl} from '@angular/forms';

@Component({
    selector: 'app-selector-ofe',
    templateUrl: './selector-ofe.component.html',
    styleUrls: ['./selector-ofe.component.scss']
})
export class SelectorOfeComponent extends BaseComponent implements OnInit, OnDestroy {

    @Input() ofe_id: AbstractControl = null;
    @Input() ver: boolean;
    @Input() multiple: boolean;

    // Observables
    oferentes$: Observable<Oferente[]>;
    oferentesInput$ = new Subject<string>();
    oferentesLoading: boolean = false;
    selectedOfeId: any;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    constructor(private _oferentesServices: OferentesService, private cd: ChangeDetectorRef) {
        super();
    }

    ngOnInit() {
        const vacioOfes: Oferente[] = [];
        this.oferentes$ = concat(
            of(vacioOfes),
            this.oferentesInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._oferentesServices.searchOferentesNgSelect(term).pipe(
                    catchError(() => of(vacioOfes)),
                    tap(() => this.loading(false))
                ))
            ));
        if(this.ver){
            this.ofe_id.disable();
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
