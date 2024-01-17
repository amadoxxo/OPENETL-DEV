import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {MatButtonModule} from '@angular/material/button';
import {MatIconModule} from '@angular/material/icon';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {SelectorOfePrecargadoModule} from '../../commons/selector-ofe-precargado/selector-ofe-precargado.module';
import {MagicFieldsModule} from '../../commons/magic-fields/magic-fields.module';
import {MatRadioModule} from '@angular/material/radio';
import {MatSelectModule} from '@angular/material/select';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatInputModule} from '@angular/material/input';
import {MatExpansionModule} from '@angular/material/expansion';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {OpenTrackingModule} from '../../commons/open-tracking/open-tracking.module';
import {LogErroresModule} from '../../commons/log-errores/log-errores.module';
import {CargasMasivasModule} from '../../commons/cargas-masivas/cargas-masivas.module';

import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {CommonsService} from '../../../services/commons/commons.service';

import {ResolucionesFacturacionGestionarComponent} from './gestionar/resoluciones-facturacion-gestionar.component';
import {ResolucionesFacturacionListarComponent} from './listar/resoluciones-facturacion-listar.component';
import {ResolucionesFacturacionSubirComponent} from './subir/resoluciones-facturacion-subir.component';
import {ResolucionesFacturacionRoutingModule} from './resoluciones-facturacion.routing';
import {ModalConsultaResolucionDianModule} from '../../commons/modal-consulta-resolucion-dian/modal-consulta-resolucion-dian.module';

@NgModule({
    declarations: [
        ResolucionesFacturacionGestionarComponent,
        ResolucionesFacturacionListarComponent,
        ResolucionesFacturacionSubirComponent
    ],
    imports: [
        ResolucionesFacturacionRoutingModule,
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
        MatRadioModule,
        SelectorOfePrecargadoModule,
        MatDatepickerModule,
        MatExpansionModule,
        ModalConsultaResolucionDianModule
    ],
    providers: [
        OferentesService,
        CommonsService
    ],
    exports: [],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA]
})

export class ResolucionesFacturacionModule {}
