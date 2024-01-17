import {Component, Input, OnInit, OnDestroy, Output, EventEmitter} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {concat, Observable, of, Subject} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, filter, switchMap, tap} from 'rxjs/operators';
import {Trabajador} from '../../models/trabajador.model';
import {ConfiguracionService} from '../../../services/configuracion/configuracion.service';
import {AbstractControl} from '@angular/forms';

@Component({
    selector: 'app-selector-par-empleador-trabajador',
    templateUrl: './selector-par-empleador-trabajador.component.html',
    styleUrls: ['./selector-par-empleador-trabajador.component.scss']
})
export class SelectorParEmpleadorTrabajadorComponent extends BaseComponent implements OnInit, OnDestroy {

    @Input() emp_id: AbstractControl = null;
    @Input() tra_id: AbstractControl = null;
    @Input() empleadores: Array<any> = [];
    @Input() selector_multiple_trabajador?: boolean = false;

    @Output() empSeleccionado = new EventEmitter();
    @Output() traSeleccionado = new EventEmitter();

    trabajadores$: Observable<Trabajador[]>;
    trabajadoresInput$ = new Subject<string>();
    trabajadoresLoading: boolean = false;
    selectedTraId: any;

    selectedEmpleadorId: any;
    arrTraBusqueda: any;
    constructor(private _configuracionServices: ConfiguracionService) {
        super();
    }

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    ngOnInit() {
        this.listarTrabajadores();
    }

    listarTrabajadores() {
        const vacioTrabajadores: Trabajador[] = [];
        this.trabajadores$ = concat(
            of(vacioTrabajadores),
            this.trabajadoresInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._configuracionServices.searchTrabajadoresNgSelect(term, this.selectedEmpleadorId).pipe(
                    catchError(() => of(vacioTrabajadores)),
                    tap((data) => {
                        this.loading(false);
                        this.arrTraBusqueda = data;
                        this.arrTraBusqueda.forEach( (trabajador) => {
                            trabajador['tra_identificacion_nombre_completo'] = '('+trabajador['tra_identificacion'] + ') - ' + trabajador['nombre_completo'];
                        });
                    } 
                )))
            )
        );
    }

    /**
     * Realiza la búsqueda personalizada de un empleador.
     *
     * @param {string} term Texto de búsqueda
     * @param {*} item Información del empleador
     * @return {*} 
     * @memberof SelectorParEmpleadorTrabajadorComponent
     */
    customSearchFnEmpleador(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.emp_identificacion.toLocaleLowerCase().indexOf(term) > -1 || item.nombre_completo.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Obtiene el evento cuando se selecciona un empleador.
     *
     * @param {*} empleador Información del empleador seleccionado
     * @memberof SelectorParEmpleadorTrabajadorComponent
     */
    onEmpleadorSeleccionado(empleador) {
        this.tra_id.enable();
        this.selectedTraId = null;
        this.listarTrabajadores();
        if(empleador)
            this.empSeleccionado.emit(empleador);
    }

    /**
     * Obtiene el evento cuando se selecciona un empleador.
     *
     * @param {*} trabajador Información del trabajador seleccionado
     * @memberof SelectorParEmpleadorTrabajadorComponent
     */
    onTrabajadorSeleccionado(trabajador) {
        if(trabajador)
            this.traSeleccionado.emit(trabajador);
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

    /**
     * Permite limpiar el select de empleador y trabajador.
     *
     * @memberof SelectorParEmpleadorTrabajadorComponent
     */
    clear(){
        this.selectedTraId = null;
        this.tra_id.disable();
        this.listarTrabajadores();
    }

    /**
     * Limpia los registros seleccionados en el ng-select del Receptor.
     * 
     * @memberof SelectorParEmpleadorTrabajadorComponent
     */
    onLimpiarTrabajadorReg() {
        this.tra_id.patchValue([]);
    }

    /**
     * Selecciona todos los registros filtrados en el ng-select del Receptor.
     * 
     * @memberof SelectorParEmpleadorTrabajadorComponent
     */
    onSeleccionarTodosTrabajadores() {
        let arrTodosReceptores = this.arrTraBusqueda.map( (data) => {
            this.traSeleccionado.emit(data);
            return data.tra_id;
        });
        this.tra_id.patchValue(arrTodosReceptores);
    }
}
