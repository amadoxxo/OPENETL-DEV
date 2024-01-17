import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {OpenTrackingComponent} from './open-tracking.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MatButtonModule} from '@angular/material/button';
import {MatCheckboxModule} from '@angular/material/checkbox';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {MatOptionModule} from '@angular/material/core';
import {MatSelectModule} from '@angular/material/select';
import {MatTooltipModule} from '@angular/material/tooltip';
import {MatMenuModule} from '@angular/material/menu';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {ModalUsuariosPortalesModule} from '../modal-usuarios-portales/modal-usuarios-portales.module';

@NgModule({
    declarations: [OpenTrackingComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        MatButtonModule,
        MatIconModule,
        MatCheckboxModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatMenuModule,
        MatOptionModule,
        MatTooltipModule,
        NgxDatatableModule,
        ModalUsuariosPortalesModule
    ],
    exports: [
        OpenTrackingComponent
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class OpenTrackingModule {}
