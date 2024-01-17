import { LoaderModule } from 'app/shared/loader/loader.module';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatRadioModule } from '@angular/material/radio';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { FuseSharedModule } from '@fuse/shared.module';
import { MatDividerModule } from '@angular/material/divider';
import { FlexLayoutModule } from '@angular/flex-layout';
import { MatFormFieldModule } from '@angular/material/form-field';
import { ModalGestionFeDsComponent } from './modal-gestion-fe-ds.component';
import { CUSTOM_ELEMENTS_SCHEMA, NgModule } from '@angular/core';

@NgModule({
    declarations: [ ModalGestionFeDsComponent ],
    exports: [ ModalGestionFeDsComponent ],
    imports: [
        CommonModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatDividerModule,
        MatRadioModule,
        FlexLayoutModule,
        MatSelectModule,
        MatFormFieldModule,
        MatInputModule,
        MatTooltipModule,
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class ModalGestionFeDsModule {}

