import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorLoteComponent} from './selector-lote.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {NgSelectModule} from '@ng-select/ng-select';


@NgModule({
    declarations: [SelectorLoteComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule
    ],
    exports: [SelectorLoteComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [OferentesService]
})
export class SelectorLoteModule {}
