import {NgModule} from '@angular/core';
import {MatIconModule} from '@angular/material/icon';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatRadioModule} from '@angular/material/radio';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MagicFieldsModule} from '../../../commons/magic-fields/magic-fields.module';

import {AuthGuard} from 'app/auth.guard';
import {LoaderModule} from 'app/shared/loader/loader.module';
import {TiemposAceptacionTacitaGestionarComponent} from './tiempos_aceptacion_tacita_gestionar.component';


@NgModule({
    declarations: [
        TiemposAceptacionTacitaGestionarComponent
    ],
    imports     : [
        FuseSharedModule,
        MagicFieldsModule,
        LoaderModule,
        MatIconModule,
        MatFormFieldModule,
        MatDialogModule, 
        MatInputModule,
        MatRadioModule,
        MatButtonModule
    ],
    providers   : [
        AuthGuard,
    ]
})

export class TiemposAceptacionTacitaGestionarModule {}