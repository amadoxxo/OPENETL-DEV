import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {MatButtonModule} from '@angular/material/button';
import {MatIconModule} from '@angular/material/icon';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MagicFieldsModule} from '../../commons/magic-fields/magic-fields.module';
import {MatRadioModule} from '@angular/material/radio';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatInputModule} from '@angular/material/input';
import {MatSelectModule} from '@angular/material/select';
import {MatExpansionModule} from '@angular/material/expansion';
import {MatDividerModule} from '@angular/material/divider';
import {UbicacionOpenModule} from '../../commons/ubicacion-open/ubicacion-open.module';
import {InformacionAdicionalModule} from '../../commons/informacion-adicional/informacion-adicional.module';
import {NotificacionesModule} from '../../commons/notificaciones/notificaciones.module';
import {DatosGeneralesRegistroModule} from '../../commons/datos-generales-registro/datos-generales-registro.module';
import {DatosTributariosModule} from '../../commons/datos-tributarios/datos-tributarios.module';
import {SelectorOfePrecargadoModule} from '../../commons/selector-ofe-precargado/selector-ofe-precargado.module';
import {TiemposAceptacionTacitaModule} from '../../sistema/tiempos_aceptacion_tacita/listar/tiempos_aceptacion_tacita.module';
import {AceptacionTacitaModule} from '../../commons/aceptacion-tacita/aceptacion-tacita.module';
import {ContactFormGroupControlModule} from '../../commons/contact-form-group-control/contact-form-group-control.module';
import {OpenTrackingModule} from '../../commons/open-tracking/open-tracking.module';
import {LogErroresModule} from '../../commons/log-errores/log-errores.module';
import {CargasMasivasModule} from '../../commons/cargas-masivas/cargas-masivas.module';
import {AdquirentesRoutingModule} from './adquirentes.routing';

import {AdquirentesGestionarComponent} from './adquirentes-base/gestionar/adquirentes-gestionar.component';
import {AdquirentesSubirComponent} from './adquirentes-base/subir/adquirentes-subir.component';
import {AdquirentesListarComponent} from './adquirentes-base/listar/adquirentes-listar.component';
import {AdquirentesAdquirentesGestionarComponent} from './adquirentes-adquirentes/gestionar/adquirentes-adquirentes-gestionar.component';
import {AdquirentesAdquirentesSubirComponent} from './adquirentes-adquirentes/subir/adquirentes-adquirentes-subir.component';
import {AdquirentesAdquirentesListarComponent} from './adquirentes-adquirentes/listar/adquirentes-adquirentes-listar.component';
import {AdquirentesAutorizadosGestionarComponent} from './adquirentes-autorizados/gestionar/adquirentes-autorizados-gestionar.component';
import {AdquirentesAutorizadosSubirComponent} from './adquirentes-autorizados/subir/adquirentes-autorizados-subir.component';
import {AdquirentesAutorizadosListarComponent} from './adquirentes-autorizados/listar/adquirentes-autorizados-listar.component';
import {AdquirentesResponsablesGestionarComponent} from './adquirentes-responsables/gestionar/adquirentes-responsables-gestionar.component';
import {AdquirentesResponsablesSubirComponent} from './adquirentes-responsables/subir/adquirentes-responsables-subir.component';
import {AdquirentesResponsablesListarComponent} from './adquirentes-responsables/listar/adquirentes-responsables-listar.component';
import {AdquirentesVendedoresGestionarComponent} from './adquirentes-vendedores/gestionar/adquirentes-vendedores-gestionar.component';
import {AdquirentesVendedoresSubirComponent} from './adquirentes-vendedores/subir/adquirentes-vendedores-subir.component';
import {AdquirentesVendedoresListarComponent} from './adquirentes-vendedores/listar/adquirentes-vendedores-listar.component';

import {ParametrosService} from '../../../services/parametros/parametros.service';
import {AdquirentesService} from '../../../services/configuracion/adquirentes.service';
import {CommonsService} from '../../../services/commons/commons.service';

@NgModule({
    declarations: [
        AdquirentesGestionarComponent,
        AdquirentesListarComponent,
        AdquirentesSubirComponent,
        AdquirentesAdquirentesGestionarComponent,
        AdquirentesAdquirentesListarComponent,
        AdquirentesAdquirentesSubirComponent,
        AdquirentesAutorizadosGestionarComponent,
        AdquirentesAutorizadosListarComponent,
        AdquirentesAutorizadosSubirComponent,
        AdquirentesResponsablesGestionarComponent,
        AdquirentesResponsablesListarComponent,
        AdquirentesResponsablesSubirComponent,
        AdquirentesVendedoresGestionarComponent,
        AdquirentesVendedoresListarComponent,
        AdquirentesVendedoresSubirComponent
    ],
    imports: [
        AdquirentesRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MagicFieldsModule,
        DatosGeneralesRegistroModule,
        UbicacionOpenModule,
        DatosTributariosModule,
        SelectorOfePrecargadoModule,
        TiemposAceptacionTacitaModule,
        ContactFormGroupControlModule,
        MatButtonModule,
        MatIconModule,
        MatFormFieldModule,
        MatSelectModule,
        MatInputModule,
        AceptacionTacitaModule,
        OpenTrackingModule,
        LogErroresModule,
        CargasMasivasModule,
        InformacionAdicionalModule,
        NotificacionesModule,
        MatRadioModule,
        MatExpansionModule,
        MatDividerModule
    ],
    providers: [
        AdquirentesService,
        CommonsService,
        ParametrosService
    ],
    exports: [
        AdquirentesGestionarComponent,
        AdquirentesListarComponent,
        AdquirentesSubirComponent
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class AdquirentesModule {}
