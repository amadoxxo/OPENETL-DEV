import {Component, Input, OnDestroy, OnInit, ChangeDetectorRef} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {concat, Observable, of, Subject} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, filter, switchMap, tap} from 'rxjs/operators';
import {AbstractControl} from '@angular/forms';
import {UsuariosService} from '../../../services/sistema/usuarios.service';
import {Usuario} from '../../models/usuario.model';

@Component({
    selector: 'app-selector-usuarios',
    templateUrl: './selector-usuario.component.html',
    styleUrls: ['./selector-usuario.component.scss']
})
export class SelectorUsuarioComponent extends BaseComponent implements OnInit, OnDestroy {

    @Input() usu_id: AbstractControl = null;
    @Input() ver: boolean;
    @Input() multiple: boolean;

    // Observables
    usuarios$: Observable<Usuario[]>;
    usuariosInput$ = new Subject<string>();
    usuariosLoading = false;
    selectedUsuIds: any [] = [];

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    constructor(private _usuariosServices: UsuariosService, private cd: ChangeDetectorRef) {
        super();
    }

    ngOnInit() {
        const vacioUsus: Usuario[] = [];
        this.usuarios$ = concat(
            of(vacioUsus),
            this.usuariosInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._usuariosServices.searchUsuariosNgSelect(term).pipe(
                    catchError(() => of(vacioUsus)),
                    tap(() => this.loading(false))
                ))
            ));
        if (this.ver){
            this.usu_id.disable();
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
