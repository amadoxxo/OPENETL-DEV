import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {DatosTributariosComponent} from './datos-tributarios.component';
import {MatIconModule} from '@angular/material/icon';
import {NgSelectModule} from '@ng-select/ng-select';

@NgModule({
    declarations: [
        DatosTributariosComponent
    ],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule,
        MatIconModule
    ],
    exports: [DatosTributariosComponent],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
})
export class DatosTributariosModule {
}
