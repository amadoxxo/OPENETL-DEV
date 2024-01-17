import {NgModule} from '@angular/core';
import {MatOptionModule} from '@angular/material/core';
import {MatIconModule} from '@angular/material/icon';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatRadioModule} from '@angular/material/radio';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {MatAutocompleteModule} from '@angular/material/autocomplete';
import {MagicFieldsModule} from '../../../commons/magic-fields/magic-fields.module';

import {AuthGuard} from 'app/auth.guard';
import {LoaderModule} from 'app/shared/loader/loader.module';
import {VariablesSistemaGestionarComponent} from './variables_sistema_gestionar.component';


@NgModule({
    declarations: [
        VariablesSistemaGestionarComponent
    ],
    imports     : [
        FuseSharedModule,
        MagicFieldsModule,
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
    providers   : [
        AuthGuard,
    ]
})

export class VariablesSistemaGestionarModule {}