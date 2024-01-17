import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {LoaderModule} from '../../../shared/loader/loader.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {ModalInformacionMonedaDocumentoComponent} from './modal-informacion-moneda-documento.component';

@NgModule({
    declarations: [ModalInformacionMonedaDocumentoComponent],
    exports: [ModalInformacionMonedaDocumentoComponent],
    imports: [
        CommonModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        NgxDatatableModule,
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class ModalInformacionMonedaDocumentoModule {}

