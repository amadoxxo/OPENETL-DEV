import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorParEmisorReceptorComponent} from './selector-par-emisor-receptor.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {NgSelectModule} from '@ng-select/ng-select';


@NgModule({
    declarations: [SelectorParEmisorReceptorComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule
    ],
    exports: [SelectorParEmisorReceptorComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [OferentesService]
})
export class SelectorParEmisorReceptorModule {}
