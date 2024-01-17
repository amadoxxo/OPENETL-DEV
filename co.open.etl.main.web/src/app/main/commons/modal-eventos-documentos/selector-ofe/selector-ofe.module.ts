import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorOfeComponent} from './selector-ofe.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {NgSelectModule} from '@ng-select/ng-select';


@NgModule({
    declarations: [SelectorOfeComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule
    ],
    exports: [SelectorOfeComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [OferentesService]
})
export class SelectorOfeModule {}
