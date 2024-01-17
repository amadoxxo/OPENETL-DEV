import {Component, Input, OnInit, OnDestroy, Output, EventEmitter} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {concat, Observable, of, Subject} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, filter, switchMap, tap} from 'rxjs/operators';
import {Proveedor} from '../../models/proveedor.model';
import {ProveedoresService} from '../../../services/configuracion/proveedores.service';
import {AbstractControl} from '@angular/forms';

@Component({
    selector: 'app-selector-par-receptor-emisor',
    templateUrl: './selector-par-receptor-emisor.component.html',
    styleUrls: ['./selector-par-receptor-emisor.component.scss']
})
export class SelectorParReceptorEmisorComponent extends BaseComponent implements OnInit, OnDestroy {
    @Input() ofe_id                     : AbstractControl = null;
    @Input() pro_id                     : AbstractControl = null;
    @Input() oferentes                  : Array<any> = [];
    @Input() selector_multiple_receptor?: boolean = false;
    @Input() disabled_controls?         : boolean = false;

    @Output() ofeSeleccionado = new EventEmitter();
    @Output() proSeleccionado = new EventEmitter();

    proveedores$      : Observable<Proveedor[]>;
    proveedoresInput$ = new Subject<string>();
    proveedoresLoading: boolean = false;
    selectedProId     : any;
    selectedOfeId     : any;
    arrProvBusqueda   : any;

    constructor(private _proveedoresServices: ProveedoresService) {
        super();
    }

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    ngOnInit() {
        this.listarProveedores();
    }

    listarProveedores() {
        const vacioProvs: Proveedor[] = [];
        this.proveedores$ = concat(
            of(vacioProvs),
            this.proveedoresInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._proveedoresServices.searchProveedorNgSelect(term, this.selectedOfeId).pipe(
                    catchError(() => of(vacioProvs)),
                    tap((data) => {
                        this.loading(false);
                        this.arrProvBusqueda = data;
                        this.arrProvBusqueda.forEach( (prov) => {
                            prov['pro_identificacion_pro_razon_social'] = '('+prov['pro_identificacion'] + ') - ' + prov['pro_razon_social'];
                        });
                    }
                )))
            )
        );
    }

    customSearchFnOfe(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.ofe_identificacion.toLocaleLowerCase().indexOf(term) > -1 || item.ofe_razon_social.toLocaleLowerCase().indexOf(term) > -1;
    }

    onOfeSeleccionado(ofe) {
        this.pro_id.enable();
        this.selectedProId = null;
        this.listarProveedores();

        if(ofe)
            this.ofeSeleccionado.emit(ofe);
    }

    onProSeleccionado(pro) {
        this.proSeleccionado.emit('');
        if(pro)
            this.proSeleccionado.emit(pro);
    }

    /**
     * On destroy.
     * 
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
    }

    clear(){
        this.selectedProId = null;
        this.pro_id.disable();
        this.listarProveedores();
    }

    /**
     * Limpia los registros seleccionados en el ng-select del Emisor.
     * 
     * @memberof SelectorParReceptorEmisorComponent
     */
    onLimpiarEmisorReg() {
        this.pro_id.patchValue([]);
    }

    /**
     * Selecciona todos los registros filtrados en el ng-select del Emisor.
     * 
     * @memberof SelectorParReceptorEmisorComponent
     */
    onSeleccionarTodosEmisor() {
        let arrTodosEmisores = this.arrProvBusqueda.map( (data) => {
            return data.pro_id;
        });
        this.pro_id.patchValue(arrTodosEmisores);
    }
}
