import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {MatButtonModule} from '@angular/material/button';
import {MatIconModule} from '@angular/material/icon';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '../../../../../@fuse/shared.module';
import {MagicFieldsModule} from '../../../commons/magic-fields/magic-fields.module';
import {MatRadioModule} from '@angular/material/radio';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatInputModule} from '@angular/material/input';
import {MatExpansionModule} from '@angular/material/expansion';
import {MatAutocompleteModule} from '@angular/material/autocomplete';
import {MatCheckboxModule} from '@angular/material/checkbox';
import {MatSelectModule} from '@angular/material/select';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {LogErroresModule} from '../../../commons/log-errores/log-errores.module';
import {OpenTrackingModule} from '../../../commons/open-tracking/open-tracking.module';
import {UbicacionOpenModule} from '../../../commons/ubicacion-open/ubicacion-open.module';
import {CargasMasivasModule} from '../../../commons/cargas-masivas/cargas-masivas.module';
import {TrabajadoresRoutingModule} from './trabajadores.routing';

import {CommonsService} from '../../../../services/commons/commons.service';
import {ParametrosService} from '../../../../services/parametros/parametros.service';

import {TrabajadoresGestionarComponent} from './gestionar/trabajadores-gestionar.component';
import {TrabajadoresSubirComponent} from './subir/trabajadores-subir.component';
import {TrabajadoresListarComponent} from './listar/trabajadores-listar.component';

@NgModule({
    declarations: [
        TrabajadoresGestionarComponent,
        TrabajadoresListarComponent,
        TrabajadoresSubirComponent
    ],
    imports: [
        TrabajadoresRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MagicFieldsModule,
        MatButtonModule,
        MatIconModule,
        MatFormFieldModule,
        MatSelectModule,
        MatInputModule,
        UbicacionOpenModule,
        OpenTrackingModule,
        LogErroresModule,
        CargasMasivasModule,
        MatRadioModule,
        MatDatepickerModule,
        MatExpansionModule,
        MatAutocompleteModule,
        NgSelectModule,
        MatCheckboxModule
    ],
    providers: [
        CommonsService,
        ParametrosService
    ],
    exports: [],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class TrabajadoresModule {}
