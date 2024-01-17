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
import {MatCheckboxModule} from '@angular/material/checkbox';
import {SelectorParReceptorEmisorModule} from '../../commons/selector-par-receptor-emisor/selector-par-receptor-emisor.module';
import {OpenTrackingModule} from '../../commons/open-tracking/open-tracking.module';
import {LogErroresModule} from '../../commons/log-errores/log-errores.module';
import {CargasMasivasModule} from '../../commons/cargas-masivas/cargas-masivas.module';
import {DatosEventoDianModule} from '../../commons/datos-evento-dian/datos-evento-dian.module';

import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {CommonsService} from '../../../services/commons/commons.service';

import {AutorizacionesEventosDianGestionarComponent} from './gestionar/autorizaciones-eventos-dian-gestionar.component';
import {AutorizacionesEventosDianListarComponent} from './listar/autorizaciones-eventos-dian-listar.component';
import {AutorizacionesEventosDianSubirComponent} from './subir/autorizaciones-eventos-dian-subir.component';
import {AutorizacionesEventosDianRoutingModule} from './autorizaciones-eventos-dian.routing';


@NgModule({
    declarations: [
        AutorizacionesEventosDianGestionarComponent,
        AutorizacionesEventosDianListarComponent,
        AutorizacionesEventosDianSubirComponent
    ],
    imports: [
        AutorizacionesEventosDianRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MagicFieldsModule,
        SelectorParReceptorEmisorModule,
        MatButtonModule,
        MatIconModule,
        MatFormFieldModule,
        MatInputModule,
        OpenTrackingModule,
        LogErroresModule,
        CargasMasivasModule,
        MatExpansionModule,
        MatAutocompleteModule,
        NgSelectModule,
        MatCheckboxModule,
        DatosEventoDianModule
    ],
    providers: [
        OferentesService,
        CommonsService
    ],
    exports: [],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class AutorizacionesEventosDianModule {
}
