import {Router} from '@angular/router';
import {Component, Input, OnInit, OnDestroy, Output, EventEmitter} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {concat, Observable, of, Subject} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, filter, switchMap, tap} from 'rxjs/operators';
import {Adquirente} from '../../models/adquirente.model';
import {AdquirentesService} from '../../../services/configuracion/adquirentes.service';
import {AbstractControl} from '@angular/forms';

@Component({
    selector: 'app-selector-par-emisor-receptor',
    templateUrl: './selector-par-emisor-receptor.component.html',
    styleUrls: ['./selector-par-emisor-receptor.component.scss']
})
export class SelectorParEmisorReceptorComponent extends BaseComponent implements OnInit, OnDestroy {

    @Input() ofe_id: AbstractControl = null;
    @Input() adq_id: AbstractControl = null;
    @Input() oferentes: Array<any> = [];
    @Input() proceso_pickup_cash: boolean = false;
    @Input() selector_multiple_receptor?: boolean = false;
    @Input() disabled_ofe?: boolean = false;

    @Output() ofeSeleccionado = new EventEmitter();
    @Output() adqSeleccionado = new EventEmitter();

    public labelOfe: string = '';
    public labelAdq: string = '';

    adquirentes$: Observable<Adquirente[]>;
    adquirentesInput$ = new Subject<string>();
    adquirentesLoading: boolean = false;
    selectedAdqId: any;

    selectedOfeId: any;
    arrAdqBusqueda: any;
    constructor(private _adquirentesServices: AdquirentesService, private _router: Router) {
        super();
    }

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    ngOnInit() {
        if (this._router.url.indexOf('crear-documento/documento-soporte') !== -1 || this._router.url.indexOf('crear-documento/ds-nota-credito') !== -1 || this._router.url.indexOf('documento-soporte') !== -1) {
            this.labelOfe = 'Receptor:';
            this.labelAdq = 'Vendedor:';
        } else {
            this.labelOfe = 'Emisor:';
            this.labelAdq = 'Receptor:';
        }

        this.listarAdquirentes();
    }

    listarAdquirentes() {
        const vacioAdqs: Adquirente[] = [];
        let vendedor_ds = false;
        if(this._router.url.indexOf('documento-soporte') !== -1 || this._router.url.indexOf('crear-documento/ds-nota-credito') !== -1) {
            vendedor_ds = true;
        }
        this.adquirentes$ = concat(
            of(vacioAdqs),
            this.adquirentesInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._adquirentesServices.searchAdquirentesNgSelect(term, this.selectedOfeId, false, this.proceso_pickup_cash, vendedor_ds).pipe(
                    catchError(() => of(vacioAdqs)),
                    tap((data) => {
                        this.loading(false);
                        this.arrAdqBusqueda = data;
                        this.arrAdqBusqueda.forEach( (adq) => {
                            if(adq['adq_id_personalizado'] != null)
                                adq['adq_identificacion_adq_razon_social'] = '('+ adq['adq_identificacion'] +' / '+ adq['adq_id_personalizado'] + ') - ' + adq['adq_razon_social'];
                            else
                                adq['adq_identificacion_adq_razon_social'] = '('+adq['adq_identificacion'] + ') - ' + adq['adq_razon_social'];
                        });
                        if(this._router.url.indexOf('emision') !== -1) {
                            this.arrAdqBusqueda = this.arrAdqBusqueda.filter(adquirente => adquirente.adq_tipo_adquirente == 'SI');
                        } else if(this._router.url.indexOf('documento-soporte') !== -1 || this._router.url.indexOf('crear-documento/ds-nota-credito') !== -1) {
                            this.arrAdqBusqueda = this.arrAdqBusqueda.filter(adquirente => adquirente.adq_tipo_vendedor_ds == 'SI');
                        }
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
        this.adq_id.enable();
        this.selectedAdqId = null;
        this.listarAdquirentes();
        if(ofe)
            this.ofeSeleccionado.emit(ofe);
    }

    onAdqSeleccionado(adq) {
        if(adq)
            this.adqSeleccionado.emit(adq);
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
        this.selectedAdqId = null;
        this.adq_id.disable();
        this.listarAdquirentes();
    }

    /**
     * Limpia los registros seleccionados en el ng-select del Receptor.
     * 
     * @memberof SelectorParEmisorReceptorComponent
     */
    onLimpiarReceptorReg() {
        this.adq_id.patchValue([]);
    }

    /**
     * Selecciona todos los registros filtrados en el ng-select del Receptor.
     * 
     * @memberof SelectorParEmisorReceptorComponent
     */
    onSeleccionarTodosReceptor() {
        let arrTodosReceptores = this.arrAdqBusqueda.map( (data) => {
            this.adqSeleccionado.emit(data);
            return data.adq_id;
        });
        this.adq_id.patchValue(arrTodosReceptores);
    }
}
