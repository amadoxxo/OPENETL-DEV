import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {MatButtonModule} from '@angular/material/button';
import {MatIconModule} from '@angular/material/icon';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {SelectorSftModule} from '../../commons/selector-sft/selector-sft.module';
import {MagicFieldsModule} from '../../commons/magic-fields/magic-fields.module';
import {MatRadioModule} from '@angular/material/radio';
import {MatCheckboxModule} from '@angular/material/checkbox';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatInputModule} from '@angular/material/input';
import {MatSelectModule} from '@angular/material/select';
import {MatExpansionModule} from '@angular/material/expansion';
import {MatDividerModule} from '@angular/material/divider';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {UbicacionOpenModule} from '../../commons/ubicacion-open/ubicacion-open.module';
import {InformacionAdicionalModule} from '../../commons/informacion-adicional/informacion-adicional.module';
import {NotificacionesModule} from '../../commons/notificaciones/notificaciones.module';
import {DatosGeneralesRegistroModule} from '../../commons/datos-generales-registro/datos-generales-registro.module';
import {DatosTributariosModule} from '../../commons/datos-tributarios/datos-tributarios.module';
import {AceptacionTacitaModule} from '../../commons/aceptacion-tacita/aceptacion-tacita.module';
import {ContactFormGroupControlModule} from '../../commons/contact-form-group-control/contact-form-group-control.module';
import {OpenTrackingModule} from '../../commons/open-tracking/open-tracking.module';
import {LogErroresModule} from '../../commons/log-errores/log-errores.module';
import {CargasMasivasModule} from '../../commons/cargas-masivas/cargas-masivas.module';
import {ValoresPorDefectoDocumentoElectronicoGestionarModule} from "./valores-por-defecto-documento-electronico-gestionar/valores-por-defecto-documento-electronico-gestionar.module";
import {NgSelectModule} from '@ng-select/ng-select';
import {RedesSocialesModule} from '../../commons/redes-sociales/redes-sociales.module';
import {TagInputModule} from 'ngx-chips';
import {DatosEventoDianModule} from '../../commons/datos-evento-dian/datos-evento-dian.module';

import {OferentesGestionarComponent} from './gestionar/oferentes-gestionar.component';
import {OferentesSubirComponent} from './subir/oferentes-subir.component';
import {OferentesListarComponent} from './listar/oferentes-listar.component';
import {ConfiguracionServiciosComponent} from './configuracion-servicios/configuracion-servicios.component';
import {ConfiguracionDocumentoElectronicoComponent} from './configuracion-documento-electronico/configuracion-documento-electronico.component';
import {ValoresPorDefectoDocumentoElectronicoComponent} from './valores-por-defecto-documento-electronico/valores-por-defecto-documento-electronico.component';
import {ConfiguracionDocumentoBaseComponent} from './configuracion-documento-base/configuracion-documento-base.component';
import {ConfiguracionDocumentoSoporteComponent} from './configuracion-documento-soporte/configuracion-documento-soporte.component';

import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {CommonsService} from '../../../services/commons/commons.service';
import {UsuariosService} from '../../../services/sistema/usuarios.service';
import {ParametrosService} from '../../../services/parametros/parametros.service';
import {OferentesRoutingModule} from './oferentes.routing';


@NgModule({
    declarations: [
        OferentesGestionarComponent,
        OferentesListarComponent,
        OferentesSubirComponent,
        ConfiguracionDocumentoElectronicoComponent,
        ValoresPorDefectoDocumentoElectronicoComponent,
        ConfiguracionServiciosComponent,
        ConfiguracionDocumentoSoporteComponent,
        ConfiguracionDocumentoBaseComponent
    ],
    imports: [
        OferentesRoutingModule,
        TranslateModule,
        NgSelectModule,
        FuseSharedModule,
        MagicFieldsModule,
        DatosGeneralesRegistroModule,
        UbicacionOpenModule,
        DatosTributariosModule,
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
        MatCheckboxModule,
        SelectorSftModule,
        MatExpansionModule,
        MatDividerModule,
        MatDatepickerModule,
        TagInputModule,
        ValoresPorDefectoDocumentoElectronicoGestionarModule,
        RedesSocialesModule,
        DatosEventoDianModule
    ],
    providers: [
        OferentesService,
        CommonsService,
        UsuariosService,
        ParametrosService
    ],
    exports: [],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class OferentesModule {}
