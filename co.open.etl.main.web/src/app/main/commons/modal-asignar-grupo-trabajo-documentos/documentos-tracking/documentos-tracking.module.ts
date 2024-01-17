import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {DocumentosTrackingComponent} from './documentos-tracking.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {NgSelectModule} from '@ng-select/ng-select';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatOptionModule } from '@angular/material/core';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {ModalResumenEstadosDocumentoModule} from '../modal-resumen-estados-documento/modal-resumen-estados-documento.module';
import {ModalNotificacionDocumentoModule} from '../modal-notificacion-documento/modal-notificacion-documento.module';
import {ModalReemplazarPdfModule} from '../modal-reemplazar-pdf/modal-reemplazar-pdf.module';
import {ModalCorreosRecibidosModule} from '../modal-correos-recibidos/modal-correos-recibidos.module';

@NgModule({
    declarations: [DocumentosTrackingComponent],
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
        MatInputModule,
        MatSelectModule,
        MatOptionModule,
        MatTooltipModule,
        NgxDatatableModule,
        ModalResumenEstadosDocumentoModule,
        ModalNotificacionDocumentoModule,
        ModalReemplazarPdfModule,
        ModalCorreosRecibidosModule
    ],
    exports: [
        DocumentosTrackingComponent
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class DocumentosTrackingModule {}
