import {NgModule} from '@angular/core';
import {MatOptionModule} from '@angular/material/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatRadioModule} from '@angular/material/radio';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {MatAutocompleteModule} from '@angular/material/autocomplete';

import {AuthGuard} from 'app/auth.guard';
import {LoaderModule} from 'app/shared/loader/loader.module';
import {ModalDocumentosAnexosComponent} from './modal-documentos-anexos.component';


@NgModule({
    declarations: [
        ModalDocumentosAnexosComponent
    ],
    imports: [
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatFormFieldModule,
        MatDialogModule, 
        MatDatepickerModule,
        MatInputModule,
        MatRadioModule,
        MatOptionModule,
        MatAutocompleteModule,
        MatButtonModule
    ],
    providers: [
        AuthGuard,
    ]
})

export class ModalDocumentosAnexosModule {}