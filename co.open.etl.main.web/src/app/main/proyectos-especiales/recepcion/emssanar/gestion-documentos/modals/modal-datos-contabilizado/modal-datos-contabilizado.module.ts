import { LoaderModule } from 'app/shared/loader/loader.module';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { FuseSharedModule } from '@fuse/shared.module';
import { MatDividerModule } from '@angular/material/divider';
import { FlexLayoutModule } from '@angular/flex-layout';
import { MatFormFieldModule } from '@angular/material/form-field';
import { CUSTOM_ELEMENTS_SCHEMA, NgModule } from '@angular/core';
import { ModalDatosContabilizadoComponent } from './modal-datos-contabilizado.component';

@NgModule({
    declarations: [ ModalDatosContabilizadoComponent ],
    exports: [ ModalDatosContabilizadoComponent ],
    imports: [
        CommonModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatDividerModule,
        FlexLayoutModule,
        MatFormFieldModule,
        MatInputModule,
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class ModalDatosContabilizadoModule {}

