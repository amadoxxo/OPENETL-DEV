import {NgModule} from '@angular/core';
import {AuthGuard} from 'app/auth.guard';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {UsuariosSubirComponent} from './usuarios_subir.component';
import {LogErroresModule} from '../../../commons/log-errores/log-errores.module';
import {CargasMasivasModule} from '../../../commons/cargas-masivas/cargas-masivas.module';
import { UsuariosSubirRoutingModule } from './usuarios_subir.routing';

@NgModule({
    declarations: [
        UsuariosSubirComponent
    ],
    imports     : [
        UsuariosSubirRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        LogErroresModule, 
        CargasMasivasModule
    ],
    providers   : [
        AuthGuard,
    ]
})

export class UsuariosSubirModule {}
