import {NgModule} from '@angular/core';
import {MatIconModule} from '@angular/material/icon';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatRadioModule} from '@angular/material/radio';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {MagicFieldsModule} from '../../../commons/magic-fields/magic-fields.module';

import {AuthGuard} from 'app/auth.guard';
import {LoaderModule} from 'app/shared/loader/loader.module';
import {FestivosGestionarComponent} from './festivos_gestionar.component';


@NgModule({
    declarations: [
        FestivosGestionarComponent
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
        MatButtonModule
    ],
    providers   : [
        AuthGuard,
    ]
})

export class FestivosGestionarModule {}