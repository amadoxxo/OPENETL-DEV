import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { CommonsService } from 'app/services/commons/commons.service';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { FuseSharedModule } from '../../../../@fuse/shared.module';
import { MatFormFieldModule } from '@angular/material/form-field';
import { SelectorParFechasModule } from '../selector-par-fechas/selector-par-fechas.module';
import { SelectorParReceptorEmisorModule } from '../selector-par-receptor-emisor/selector-par-receptor-emisor.module';
import { CUSTOM_ELEMENTS_SCHEMA, NgModule } from '@angular/core';
import { FiltrosGestionDocumentosComponent } from './filtros-gestion-documentos.component';

@NgModule({
    declarations: [FiltrosGestionDocumentosComponent],
    imports: [
        FuseSharedModule,
        MatIconModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatTooltipModule,
        CommonModule,
        FormsModule,
        SelectorParReceptorEmisorModule,
        SelectorParFechasModule,
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
    exports: [FiltrosGestionDocumentosComponent],
    providers: [CommonsService]
})
export class FiltrosGestionDocumentosModule {}

