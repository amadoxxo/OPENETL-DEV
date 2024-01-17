import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {AceptacionTacitaComponent} from './aceptacion-tacita.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {NgSelectModule} from '@ng-select/ng-select';

@NgModule({
    declarations: [AceptacionTacitaComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule,
    ],
    exports: [AceptacionTacitaComponent],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
})
export class AceptacionTacitaModule {
}
