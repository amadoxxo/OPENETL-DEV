import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {DatosGeneralesRegistroComponent} from './datos-generales-registro.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {FuseSharedModule} from '../../../../@fuse/shared.module';

@NgModule({
    declarations: [DatosGeneralesRegistroComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        NgSelectModule,
        MatIconModule,
        MatFormFieldModule,
        MatInputModule,
        FuseSharedModule,
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
    exports: [DatosGeneralesRegistroComponent]
})
export class DatosGeneralesRegistroModule {}

