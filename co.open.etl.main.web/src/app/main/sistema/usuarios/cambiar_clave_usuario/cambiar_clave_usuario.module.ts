import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';

import {AuthGuard} from 'app/auth.guard';
import {LoaderModule} from 'app/shared/loader/loader.module';
import {CambiarClaveUsuarioComponent} from './cambiar_clave_usuario.component';


@NgModule({
    declarations: [
        CambiarClaveUsuarioComponent
    ],
    imports     : [
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatFormFieldModule,
        MatDialogModule, 
        MatInputModule,
        MatButtonModule
    ],
    providers   : [
        AuthGuard,
    ]
})

export class CambiarClaveUsuarioModule {}