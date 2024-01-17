import {NgModule} from '@angular/core';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '@fuse/shared.module';
// Services
import {RolesService} from '../../services/sistema/roles.service';
import {UsuariosService} from '../../services/sistema/usuarios.service';
import {SistemaService} from '../../services/sistema/sistema.service';
import { SistemaRoutingModule } from './sistema.routing';

@NgModule({
    imports: [
        SistemaRoutingModule,
        TranslateModule,
        FuseSharedModule,
    ],
    providers: [
        RolesService,
        UsuariosService,
        SistemaService
    ]
})

export class SistemaModule {
}
