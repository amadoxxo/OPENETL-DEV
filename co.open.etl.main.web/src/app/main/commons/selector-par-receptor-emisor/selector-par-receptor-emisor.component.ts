import { Proveedor } from '../../models/proveedor.model';
import { BaseComponent } from '../../core/base_component';
import { AbstractControl } from '@angular/forms';
import { ProveedoresService } from '../../../services/configuracion/proveedores.service';
import { GestionDocumentosService } from './../../../services/proyectos-especiales/recepcion/emssanar/gestion-documentos.service';
import { concat, Observable, of, Subject } from 'rxjs';
import { Component, Input, OnInit, OnDestroy, Output, EventEmitter } from '@angular/core';
import { catchError, debounceTime, distinctUntilChanged, filter, switchMap, tap } from 'rxjs/operators';

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
    @Input() origen?                    : string = '';

    @Output() ofeSeleccionado = new EventEmitter();
    @Output() proSeleccionado = new EventEmitter();

    proveedores$      : Observable<any[]>;
    proveedoresInput$ = new Subject<string>();
    proveedoresLoading: boolean = false;
    selectedProId     : any;
    selectedOfeId     : any;
    arrProvBusqueda   : any[] = [];
    campoSelectId     : string = 'pro_id';
    campoSelectValue  : string = 'pro_identificacion_pro_razon_social';

    private _unsubscribeAll: Subject<any> = new Subject();

    /**
     * Crea una instancia de SelectorParReceptorEmisorComponent.
     * 
     * @param {ProveedoresService} _proveedoresServices
     * @param {GestionDocumentosService} _gestionDocumentosService
     * @memberof SelectorParReceptorEmisorComponent
     */
    constructor(
        private _proveedoresServices: ProveedoresService,
        private _gestionDocumentosService: GestionDocumentosService
    ) {
        super();
    }

    /**
     * Ciclo OnInit del componente.
     *
     * @memberof SelectorParReceptorEmisorComponent
     */
    ngOnInit() {
        if(this.origen === 'gestion-documentos') {
            this.campoSelectId = 'identificacion';
            this.campoSelectValue = 'identificacion_nombre_completo';
        }

        this.getDataEmisor();
    }

    /**
     * Ciclo OnDestroy del componente.
     * 
     * @memberof SelectorParReceptorEmisorComponent
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
    }

    /**
     * Consulta la lista de los proveedores con el texto predictivo.
     *
     * @memberof SelectorParReceptorEmisorComponent
     */
    listarProveedores() {
        const vacioProvs: Proveedor[] = [];
        this.proveedores$ = concat(
            of(vacioProvs),
            this.proveedoresInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => {
                    return this._proveedoresServices.searchProveedorNgSelect(term, this.selectedOfeId).pipe(
                        catchError(() => of(vacioProvs)),
                        tap((data) => {
                            this.loading(false);
                            this.arrProvBusqueda = data;
                            this.arrProvBusqueda.forEach( (prov) => {
                                prov['pro_identificacion_pro_razon_social'] = '('+prov['pro_identificacion'] + ') - ' + prov['pro_razon_social'];
                            });
                        }
                    ));
                })
            )
        );
    }

    /**
     * Consulta la lista de los emisores con el texto predictivo.
     *
     * @memberof SelectorParReceptorEmisorComponent
     */
    listarEmisores() {
        const vacioProvs: any[] = [];
        this.proveedores$ = concat(
            of(vacioProvs),
            this.proveedoresInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => {
                    return this._gestionDocumentosService.getEmisoresSearch({ buscar: term, ofe_id: this.ofe_id.value }).pipe(
                        catchError(() => of(vacioProvs)),
                        tap((data) => {
                            this.loading(false);
                            this.arrProvBusqueda = data;
                            this.arrProvBusqueda.forEach( (prov) => {
                                prov['identificacion_nombre_completo'] = '('+prov['identificacion'] + ') - ' + prov['nombre_completo'];
                            });
                        }
                    ));
                })
            )
        );
    }

    /**
     * Realiza el filtrado de los oferentes listados.
     *
     * @param {string} term Termino a buscar
     * @param {*} item Oferente
     * @return {*} 
     * @memberof SelectorParReceptorEmisorComponent
     */
    customSearchFnOfe(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.ofe_identificacion.toLocaleLowerCase().indexOf(term) > -1 || item.ofe_razon_social.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Evento que se ejecuta cuando se selecciona un OFE.
     *
     * @param {*} ofe
     * @memberof SelectorParReceptorEmisorComponent
     */
    onOfeSeleccionado(ofe) {
        this.pro_id.enable();
        this.selectedProId = null;
        this.getDataEmisor();

        if(ofe)
            this.ofeSeleccionado.emit(ofe);
    }

    /**
     * Evento que se ejecuta cuando se selecciona un Emisor.
     *
     * @param {*} pro
     * @memberof SelectorParReceptorEmisorComponent
     */
    onProSeleccionado(pro) {
        this.proSeleccionado.emit('');
        if(pro)
            this.proSeleccionado.emit(pro);
    }

    /**
     * Realiza la búsqueda de los registros para el campo Emisor.
     *
     * @memberof SelectorParReceptorEmisorComponent
     */
    getDataEmisor() {
        if(this.origen === 'gestion-documentos') {
            this.listarEmisores();
        } else {
            this.listarProveedores();
        }
    }

    /**
     * Limpia las selecciones del campo Emisor.
     *
     * @memberof SelectorParReceptorEmisorComponent
     */
    clear(){
        this.selectedProId = null;
        this.pro_id.disable();
        this.getDataEmisor();
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
            return (this.origen === 'gestion-documentos') ? data.identificacion : data.pro_id;
        });
        this.pro_id.patchValue(arrTodosEmisores);
    }
}
