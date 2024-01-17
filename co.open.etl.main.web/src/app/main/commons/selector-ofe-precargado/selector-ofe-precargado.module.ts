import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorOfePrecargadoComponent} from './selector-ofe-precargado.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {NgSelectModule} from '@ng-select/ng-select';


@NgModule({
    declarations: [SelectorOfePrecargadoComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule
    ],
    exports: [SelectorOfePrecargadoComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [OferentesService]
})
export class SelectorOfePrecargadoModule {}
