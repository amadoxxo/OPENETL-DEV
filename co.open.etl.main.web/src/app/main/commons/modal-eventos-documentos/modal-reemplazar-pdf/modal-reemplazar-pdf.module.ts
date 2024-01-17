import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';
import {ModalReemplazarPdfComponent} from './modal-reemplazar-pdf.component';
import {DocumentosEnviadosService} from './../../../services/emision/documentos_enviados.service';

@NgModule({
    declarations: [
        ModalReemplazarPdfComponent
    ],
    imports     : [
        FuseSharedModule,
        MatIconModule,
        MatFormFieldModule,
        MatDialogModule,
        MatInputModule,
        MatButtonModule
    ],
    providers   : [
        DocumentosEnviadosService
    ]
})

export class ModalReemplazarPdfModule {}
