import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatTooltipModule} from '@angular/material/tooltip';
import {ModalUsuariosPortalesComponent} from './modal-usuarios-portales.component';
import {ProveedoresService} from '../../../services/configuracion/proveedores.service';

@NgModule({
    declarations: [
        ModalUsuariosPortalesComponent
    ],
    imports     : [
        FuseSharedModule,
        MatIconModule,
        MatFormFieldModule,
        MatDialogModule,
        MatInputModule,
        MatButtonModule,
        MatTooltipModule
    ],
    providers   : [
        ProveedoresService
    ]
})

export class ModalUsuariosPortalesModule {}