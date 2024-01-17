import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorParFechasVigenciaComponent} from './selector-par-fechas-vigencia.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MatInputModule} from '@angular/material/input';
import {MatDatepickerModule} from '@angular/material/datepicker';


@NgModule({
    declarations: [SelectorParFechasVigenciaComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        MatInputModule,
        MatDatepickerModule
    ],
    exports: [SelectorParFechasVigenciaComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: []
})
export class SelectorParFechasVigenciaModule {}
