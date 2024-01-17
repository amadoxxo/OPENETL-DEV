import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {NgSelectModule} from '@ng-select/ng-select';
import {FlexLayoutModule} from '@angular/flex-layout';
import {MatOptionModule} from '@angular/material/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatFormFieldModule} from '@angular/material/form-field';
import {SelectBusquedaPredictivaComponent} from './select-busqueda-predictiva.component';
import {BusquedasPredictivasService} from '../../../services/commons/busquedas_predictivas.service';

@NgModule({
    declarations: [
        SelectBusquedaPredictivaComponent
    ],
    imports: [
        CommonModule,
        FlexLayoutModule,
        MatFormFieldModule,
        MatButtonModule,
        MatInputModule,
        MatOptionModule,
        FuseSharedModule,
        NgSelectModule
    ],
    exports: [SelectBusquedaPredictivaComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [BusquedasPredictivasService]
})
export class SelectBusquedaPredictivaModule {}
