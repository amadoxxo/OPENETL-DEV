import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {MatButtonModule} from '@angular/material/button';
import {MatIconModule} from '@angular/material/icon';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '../../../../../@fuse/shared.module';
import {MagicFieldsModule} from '../../../commons/magic-fields/magic-fields.module';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatInputModule} from '@angular/material/input';
import {MatExpansionModule} from '@angular/material/expansion';
import {LogErroresModule} from '../../../commons/log-errores/log-errores.module';
import {SelectorSftModule} from '../../../commons/selector-sft/selector-sft.module';
import {OpenTrackingModule} from '../../../commons/open-tracking/open-tracking.module';
import {UbicacionOpenModule} from '../../../commons/ubicacion-open/ubicacion-open.module';
import {CargasMasivasModule} from '../../../commons/cargas-masivas/cargas-masivas.module';
import {RedesSocialesModule} from '../../../commons/redes-sociales/redes-sociales.module';
import {EmpleadoresRoutingModule} from './empleadores.routing';

import {CommonsService} from '../../../../services/commons/commons.service';
import {ParametrosService} from '../../../../services/parametros/parametros.service';

import {EmpleadoresGestionarComponent} from './gestionar/empleadores-gestionar.component';
import {EmpleadoresSubirComponent} from './subir/empleadores-subir.component';
import {EmpleadoresListarComponent} from './listar/empleadores-listar.component';

@NgModule({
    declarations: [
        EmpleadoresGestionarComponent,
        EmpleadoresListarComponent,
        EmpleadoresSubirComponent
    ],
    imports: [
        EmpleadoresRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MagicFieldsModule,
        MatButtonModule,
        MatIconModule,
        MatFormFieldModule,
        MatInputModule,
        SelectorSftModule,
        UbicacionOpenModule,
        OpenTrackingModule,
        LogErroresModule,
        CargasMasivasModule,
        RedesSocialesModule,
        MatExpansionModule,
        NgSelectModule
    ],
    providers: [
        CommonsService,
        ParametrosService
    ],
    exports: [],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class EmpleadoresModule {}
