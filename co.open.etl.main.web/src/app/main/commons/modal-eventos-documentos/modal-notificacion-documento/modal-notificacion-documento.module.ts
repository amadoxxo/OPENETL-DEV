import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {LoaderModule} from '../../../shared/loader/loader.module';
import {MatIconModule} from '@angular/material/icon';
import {MatDialogModule} from '@angular/material/dialog';
import {MatButtonModule} from '@angular/material/button';
import {MatDividerModule} from '@angular/material/divider';
import {MatListModule} from '@angular/material/list';
import {ModalNotificacionDocumentoComponent} from './modal-notificacion-documento.component';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatTooltipModule } from '@angular/material/tooltip';

@NgModule({
    declarations: [ModalNotificacionDocumentoComponent],
    exports: [ModalNotificacionDocumentoComponent],
    imports: [
        CommonModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatDialogModule,
        MatButtonModule,
        MatDividerModule,
        MatListModule,
        MatExpansionModule,
        MatTooltipModule
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class ModalNotificacionDocumentoModule {}
