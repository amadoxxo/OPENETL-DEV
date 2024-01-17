import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FlexLayoutModule} from '@angular/flex-layout';
import {MatOptionModule} from '@angular/material/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatSelectModule} from '@angular/material/select';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {MatFormFieldModule} from '@angular/material/form-field';
import {LogErroresComponent} from './log-errores.component';
import {LogErroresService} from '../../../services/commons/log_errores.service';

@NgModule({
    declarations: [
        LogErroresComponent
    ],
    imports: [
        CommonModule,
        FlexLayoutModule,
        MatFormFieldModule,
        MatIconModule,
        MatButtonModule,
        MatInputModule,
        MatOptionModule,
        MatSelectModule,
        FuseSharedModule,
        MatDatepickerModule,
        NgxDatatableModule
    ],
    exports: [LogErroresComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [LogErroresService]
})
export class LogErroresModule {}

