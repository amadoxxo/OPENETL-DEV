import {Subject} from 'rxjs';
import {AbstractControl} from '@angular/forms';
import {BaseComponent} from '../../core/base_component';
import {MatIconRegistry} from '@angular/material/icon';
import {DomSanitizer} from '@angular/platform-browser';
import {Component, Input, OnDestroy, OnInit} from '@angular/core';

@Component({
    selector: 'app-redes-sociales',
    templateUrl: './redes-sociales.component.html',
    styleUrls: ['./redes-sociales.component.scss']
})
export class RedesSocialesComponent extends BaseComponent implements OnInit, OnDestroy {

    @Input() sitio_web: AbstractControl = null;
    @Input() twitter: AbstractControl = null;
    @Input() facebook: AbstractControl = null;
    @Input() tipo: string;
    @Input() ver: boolean;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();
    
    /**
     * Crea una instancia de RedesSocialesComponent.
     * 
     * @param {DomSanitizer} sanitizer
     * @param {MatIconRegistry} iconRegistry
     * @memberof RedesSocialesComponent
     */
    constructor(
        private sanitizer: DomSanitizer,
        private iconRegistry: MatIconRegistry,
    ) {
        super();
        iconRegistry.addSvgIcon(
            'website',
            sanitizer.bypassSecurityTrustResourceUrl('assets/icons/material-icons/browser.svg')
        );
        iconRegistry.addSvgIcon(
            'twitter',
            sanitizer.bypassSecurityTrustResourceUrl('assets/icons/material-icons/twitter.svg')
        );
        iconRegistry.addSvgIcon(
            'facebook',
            sanitizer.bypassSecurityTrustResourceUrl('assets/icons/material-icons/facebook-nuevo.svg')
        );
    }

    ngOnInit() {}

    /**
     * On destroy
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
    }
}
