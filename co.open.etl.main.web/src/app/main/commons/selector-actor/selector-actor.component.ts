import {Component, Input, OnDestroy, Output, EventEmitter} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {Subject} from 'rxjs';
import {AbstractControl} from '@angular/forms';

@Component({
    selector: 'app-selector-actor',
    templateUrl: './selector-actor.component.html',
    styleUrls: ['./selector-actor.component.scss']
})
export class SelectorActorComponent extends BaseComponent implements OnDestroy {

    @Input() act_id                    : AbstractControl = null;
    @Input() actores                   : Array<any> = [];
    @Input() radian                    : boolean = false;

    @Output() actorSeleccionado = new EventEmitter();

    selectedActId     : any;

    constructor() {
        super();
    }

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    /**
     * Gestiona la acción de seleccionar un actor.
     *
     * @param {object} actor Data del actor seleccionado en el select 
     * @return {void}
     */
    onActorSeleccionado(actor: object): void {
        if(actor)
            this.actorSeleccionado.emit(actor);
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
     * Setea valores a las variables del Actor al limpiar el Select de ACTORES.
     *
     * @return {void}
     */
    clear(): void {
        this.selectedActId = null;
    }
}
