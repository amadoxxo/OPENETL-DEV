import { LoaderModule } from 'app/shared/loader/loader.module';
import { CommonModule } from '@angular/common';
import { FuseSharedModule } from '@fuse/shared.module';
import { MatIconModule } from '@angular/material/icon';
import { FlexLayoutModule } from '@angular/flex-layout';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatDividerModule } from '@angular/material/divider';
import { MatExpansionModule } from '@angular/material/expansion';
import { ModalVerDetalleComponent } from './modal-ver-detalle.component';
import { CUSTOM_ELEMENTS_SCHEMA, NgModule } from '@angular/core';

@NgModule({
    declarations: [ ModalVerDetalleComponent ],
    exports: [ ModalVerDetalleComponent ],
    imports: [
        CommonModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatDividerModule,
        FlexLayoutModule,
        MatInputModule,
        MatExpansionModule
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class ModalVerDetalleModule {}

