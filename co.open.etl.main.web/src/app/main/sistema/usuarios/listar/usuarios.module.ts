import {NgModule} from '@angular/core';
import {TranslateModule} from '@ngx-translate/core';
import {MatOptionModule} from '@angular/material/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatMenuModule} from '@angular/material/menu';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatSelectModule} from '@angular/material/select';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {MatTooltipModule} from '@angular/material/tooltip';
import {MatCheckboxModule} from '@angular/material/checkbox';
import {MatFormFieldModule} from '@angular/material/form-field';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {AuthGuard} from 'app/auth.guard';
import {UsuariosComponent} from './usuarios.component';
import {CambiarClaveUsuarioModule} from '../cambiar_clave_usuario/cambiar_clave_usuario.module';
import {UsuariosGestionarModule} from '../gestionar/usuarios_gestionar.module';
import {UsuariosSubirModule} from '../subir/usuarios_subir.module';
import {CommonsService} from '../../../../services/commons/commons.service';
import {UsuariosRoutingModule} from './usuarios.routing';

@NgModule({
    declarations: [
        UsuariosComponent
    ],
    imports: [
        UsuariosRoutingModule,
        FuseSharedModule,
        TranslateModule,
        LoaderModule,
        MatIconModule,
        MatCheckboxModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatMenuModule,
        MatOptionModule,
        MatTooltipModule,
        NgxDatatableModule,
        CambiarClaveUsuarioModule,
        UsuariosGestionarModule,
        UsuariosSubirModule
    ],
    providers: [
        AuthGuard,
        CommonsService
    ]
})

export class UsuariosModule {}
