import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {ModalResumenEstadosDocumentoComponent} from './modal-resumen-estados-documento.component';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {LoaderModule} from '../../../shared/loader/loader.module';
import {MatIconModule} from '@angular/material/icon';
import {MatDialogModule} from '@angular/material/dialog';
import {MatButtonModule} from '@angular/material/button';
import {MatDividerModule} from '@angular/material/divider';
import {MatListModule} from '@angular/material/list';

@NgModule({
    declarations: [ModalResumenEstadosDocumentoComponent],
    exports: [ModalResumenEstadosDocumentoComponent],
    imports: [
        CommonModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatDialogModule,
        MatButtonModule,
        MatDividerModule,
        MatListModule
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class ModalResumenEstadosDocumentoModule {}

