import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {MatButtonModule} from '@angular/material/button';
import {MatIconModule} from '@angular/material/icon';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MagicFieldsModule} from '../../commons/magic-fields/magic-fields.module';
import {MatRadioModule} from '@angular/material/radio';
import {MatSelectModule} from '@angular/material/select';
import {MatExpansionModule} from '@angular/material/expansion';
import {UbicacionOpenModule} from '../../commons/ubicacion-open/ubicacion-open.module';
import {InformacionAdicionalModule} from '../../commons/informacion-adicional/informacion-adicional.module';
import {NotificacionesModule} from '../../commons/notificaciones/notificaciones.module';
import {DatosGeneralesRegistroModule} from '../../commons/datos-generales-registro/datos-generales-registro.module';
import {DatosTributariosModule} from '../../commons/datos-tributarios/datos-tributarios.module';
import {SelectorOfePrecargadoModule} from '../../commons/selector-ofe-precargado/selector-ofe-precargado.module';
import {AceptacionTacitaModule} from '../../commons/aceptacion-tacita/aceptacion-tacita.module';
import {OpenTrackingModule} from '../../commons/open-tracking/open-tracking.module';
import {LogErroresModule} from '../../commons/log-errores/log-errores.module';
import {CargasMasivasModule} from '../../commons/cargas-masivas/cargas-masivas.module';

import {CommonsService} from '../../../services/commons/commons.service';
import {ParametrosService} from '../../../services/parametros/parametros.service';

import {ProveedoresGestionarComponent} from './gestionar/proveedores-gestionar.component';
import {ProveedoresSubirComponent} from './subir/proveedores-subir.component';
import {ProveedoresListarComponent} from './listar/proveedores-listar.component';

import {NgSelectModule} from '@ng-select/ng-select';
import {ProveedoresRoutingModule} from './proveedores.routing';


@NgModule({
    declarations: [
        ProveedoresGestionarComponent,
        ProveedoresListarComponent,
        ProveedoresSubirComponent
    ],
    imports: [
        ProveedoresRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MagicFieldsModule,
        DatosGeneralesRegistroModule,
        UbicacionOpenModule,
        DatosTributariosModule,
        SelectorOfePrecargadoModule,
        MatButtonModule,
        MatIconModule,
        MatSelectModule,
        MatExpansionModule,
        AceptacionTacitaModule,
        OpenTrackingModule,
        LogErroresModule,
        CargasMasivasModule,
        InformacionAdicionalModule,
        NotificacionesModule,
        MatRadioModule,
        NgSelectModule
    ],
    providers: [
        CommonsService,
        ParametrosService
    ],
    exports: [],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class ProveedoresModule {}
