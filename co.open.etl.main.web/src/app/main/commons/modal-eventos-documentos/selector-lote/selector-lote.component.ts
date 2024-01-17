import {AbstractControl} from '@angular/forms';
import {concat, Observable, of, Subject} from 'rxjs';
import {BaseComponent} from '../../core/base_component';
import {Documento} from '../../models/documento.model';
import {Component, Input, OnInit, OnDestroy} from '@angular/core';
import {catchError, debounceTime, distinctUntilChanged, filter, switchMap, tap} from 'rxjs/operators';
import {DocumentoNominaElectronica} from '../../models/documento-nomina-electronica.model';

import {DocumentosService} from '../../../services/emision/documentos.service';
import {DocumentosRecibidosService} from '../../../services/recepcion/documentos_recibidos.service';
import {NominaElectronicaService} from '../../../services/nomina-electronica/nomina_electronica.service';
import {RadianService} from "../../../services/radian/radian.service";

@Component({
    selector: 'app-selector-lote',
    templateUrl: './selector-lote.component.html',
    styleUrls: ['./selector-lote.component.scss']
})
export class SelectorLoteComponent extends BaseComponent implements OnInit, OnDestroy {

    @Input() cdo_lote: AbstractControl = null;
    @Input() cdn_lote: AbstractControl = null;
    @Input() enviados:string;
    @Input() recepcion:boolean = false;
    @Input() nomina_electronica?:boolean = false;
    @Input() radian?:boolean = false;

    documentos$: Observable<Documento[]>;
    documentosNominaElectronica$: Observable<DocumentoNominaElectronica[]>;
    documentosInput$ = new Subject<string>();
    documentosLoading: boolean = false;
    selectedDocId: any;

    constructor(
        private _documentosService         : DocumentosService,
        private _documentosRecibidosService: DocumentosRecibidosService,
        private _nominaElectronicaService  : NominaElectronicaService,
        private _radianService             : RadianService,
    ) {
        super();
    }

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    ngOnInit() {
        const vacioDocs: Documento[] = [];
        if(!this.recepcion && !this.nomina_electronica && !this.radian) {
            this.documentos$ = concat(
                of(vacioDocs),
                this.documentosInput$.pipe(
                    debounceTime(750),
                    filter((query: string) =>  query && query.length > 0),
                    distinctUntilChanged(),
                    tap(() => this.loading(true)),
                    switchMap(term => this._documentosService.searchDocumentosNgSelect(term, this.enviados).pipe(
                        catchError(() => of(vacioDocs)),
                        tap(() => this.loading(false))
                    ))
                ));
        } else if(this.nomina_electronica) {
            const vacioDocsNomina: DocumentoNominaElectronica[] = [];
            this.documentosNominaElectronica$ = concat(
                of(vacioDocsNomina),
                this.documentosInput$.pipe(
                    debounceTime(750),
                    filter((query: string) =>  query && query.length > 0),
                    distinctUntilChanged(),
                    tap(() => this.loading(true)),
                    switchMap(term => this._nominaElectronicaService.searchDocumentosNgSelect(term, this.enviados).pipe(
                        catchError(() => of(vacioDocsNomina)),
                        tap(() => this.loading(false))
                    ))
                ));
        } else if(this.radian) {
            this.documentos$ = concat(
                of(vacioDocs),
                this.documentosInput$.pipe(
                    debounceTime(750),
                    filter((query: string) =>  query && query.length > 0),
                    distinctUntilChanged(),
                    tap(() => this.loading(true)),
                    switchMap(term => this._radianService.searchDocumentosNgSelect('cdo_lote', term, 'basico').pipe(
                        catchError(() => of(vacioDocs)),
                        tap(() => this.loading(false))
                    ))
                ));
        } else {
            this.documentos$ = concat(
                of(vacioDocs),
                this.documentosInput$.pipe(
                    debounceTime(750),
                    filter((query: string) =>  query && query.length > 0),
                    distinctUntilChanged(),
                    tap(() => this.loading(true)),
                    switchMap(term => this._documentosRecibidosService.searchDocumentosNgSelect('cdo_lote', term, 'basico').pipe(
                        catchError(() => of(vacioDocs)),
                        tap(() => this.loading(false))
                    ))
                ));
        }
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
}
