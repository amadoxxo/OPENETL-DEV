import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorParFechasComponent} from './selector-par-fechas.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {MatDatepickerModule} from '@angular/material/datepicker';


@NgModule({
    declarations: [SelectorParFechasComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        MatIconModule,
        MatInputModule,
        MatDatepickerModule
    ],
    exports: [SelectorParFechasComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: []
})
export class SelectorParFechasModule {
}
