import { CUSTOM_ELEMENTS_SCHEMA, NgModule } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { NgSelectModule } from '@ng-select/ng-select';
import { MatIconModule } from '@angular/material/icon';
import { MatRadioModule } from '@angular/material/radio';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule } from '@angular/material/dialog';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { FuseSharedModule } from '../../../../../@fuse/shared.module';
import { LogErroresModule } from '../../../commons/log-errores/log-errores.module';
import { MagicFieldsModule } from '../../../commons/magic-fields/magic-fields.module';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { OpenTrackingModule } from '../../../commons/open-tracking/open-tracking.module';
import { CargasMasivasModule } from '../../../commons/cargas-masivas/cargas-masivas.module';
import { SelectorOfePrecargadoModule } from '../../../commons/selector-ofe-precargado/selector-ofe-precargado.module';
import { NotificacionesModule } from '../../../commons/notificaciones/notificaciones.module';

import { GruposTrabajoSubirComponent } from './subir/grupos-trabajo-subir.component';
import { GruposTrabajoListarComponent } from './listar/grupos-trabajo-listar.component';
import { GruposTrabajoGestionarComponent } from './gestionar/grupos-trabajo-gestionar.component';
import { ModalGruposTrabajoAsociadosComponent } from './modal-asociados/modal-grupos-trabajo-asociados.component';
import { GruposTrabajoAdministracionRoutingModule } from './grupos-trabajo-administracion.routing';

@NgModule({
    declarations: [
        GruposTrabajoListarComponent,
        GruposTrabajoGestionarComponent,
        GruposTrabajoSubirComponent,
        ModalGruposTrabajoAsociadosComponent
    ],
    imports: [
        GruposTrabajoAdministracionRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MatIconModule,
        MatButtonModule,
        MatCheckboxModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        OpenTrackingModule,
        LogErroresModule,
        MagicFieldsModule,
        CargasMasivasModule,
        MatRadioModule,
        NgSelectModule, 
        MatDialogModule,
        SelectorOfePrecargadoModule,
        NotificacionesModule
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class GruposTrabajoAdministracionModule {}
