import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { NgSelectModule } from '@ng-select/ng-select';
import { MatButtonModule } from '@angular/material/button';
import { FuseSharedModule } from '@fuse/shared.module';
import { FlexLayoutModule } from '@angular/flex-layout';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { NgxDatatableModule } from '@swimlane/ngx-datatable';
import { MatFormFieldModule } from '@angular/material/form-field';
import { CUSTOM_ELEMENTS_SCHEMA, NgModule } from '@angular/core';
import { GestionDocumentosTrackingComponent } from './gestion-documentos-tracking.component';

@NgModule({
    declarations: [ GestionDocumentosTrackingComponent ],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule,
        MatButtonModule,
        MatIconModule,
        MatMenuModule,
        MatCheckboxModule,
        MatFormFieldModule,
        MatTooltipModule,
        NgxDatatableModule,
    ],
    exports: [ GestionDocumentosTrackingComponent ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class GestionDocumentosTrackingModule {}
