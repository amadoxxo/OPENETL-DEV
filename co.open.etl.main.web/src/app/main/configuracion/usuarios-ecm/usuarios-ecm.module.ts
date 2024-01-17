import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {MatButtonModule} from '@angular/material/button';
import {MatIconModule} from '@angular/material/icon';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MagicFieldsModule} from '../../commons/magic-fields/magic-fields.module';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatInputModule} from '@angular/material/input';
import {MatExpansionModule} from '@angular/material/expansion';
import {MatAutocompleteModule} from '@angular/material/autocomplete';
import {MatSelectModule} from '@angular/material/select';
import {OpenTrackingModule} from '../../commons/open-tracking/open-tracking.module';
import {LogErroresModule} from '../../commons/log-errores/log-errores.module';
import {CargasMasivasModule} from '../../commons/cargas-masivas/cargas-masivas.module';
import {UsuariosEcmRoutingModule} from './usuarios-ecm.routing';

import {UsuariosEcmGestionarComponent} from './gestionar/usuarios-ecm-gestionar.component';
import {UsuariosEcmListarComponent} from './listar/usuarios-ecm-listar.component';
import {UsuariosEcmSubirComponent} from './subir/usuarios-ecm-subir.component';

import {CommonsService} from './../../../services/commons/commons.service';

@NgModule({
    declarations: [
        UsuariosEcmListarComponent,
        UsuariosEcmGestionarComponent,
        UsuariosEcmSubirComponent
    ],
    imports: [
        UsuariosEcmRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MagicFieldsModule,
        MatButtonModule,
        MatIconModule,
        MatFormFieldModule,
        MatSelectModule,
        MatInputModule,
        OpenTrackingModule,
        LogErroresModule,
        CargasMasivasModule,
        MatExpansionModule,
        MatAutocompleteModule,
        NgSelectModule
    ],
    providers: [
        CommonsService
    ],
    exports: [],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class UsuariosEcmModule {}
