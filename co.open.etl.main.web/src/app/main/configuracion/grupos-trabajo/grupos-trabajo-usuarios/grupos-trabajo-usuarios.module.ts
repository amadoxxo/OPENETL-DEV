import { CUSTOM_ELEMENTS_SCHEMA, NgModule } from '@angular/core';
import { MatOptionModule } from '@angular/material/core';
import { NgSelectModule } from '@ng-select/ng-select';
import { FuseSharedModule } from '@fuse/shared.module';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule } from '@angular/material/dialog';
import { MatSelectModule } from '@angular/material/select';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { LoaderModule } from 'app/shared/loader/loader.module';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { LogErroresModule } from '../../../commons/log-errores/log-errores.module';
import { OpenTrackingModule } from '../../../commons/open-tracking/open-tracking.module';
import { CargasMasivasModule } from '../../../commons/cargas-masivas/cargas-masivas.module';

import { GruposTrabajoUsuariosSubirComponent } from './subir/grupos-trabajo-usuarios-subir.component';
import { GruposTrabajoUsuariosListarComponent } from './listar/grupos-trabajo-usuarios-listar.component';
import { GruposTrabajoUsuariosGestionarComponent } from './gestionar/grupos-trabajo-usuarios-gestionar.component';
import { GruposTrabajoUsuariosRoutingModule } from './grupos-trabajo-usuarios.routing';


@NgModule({
    declarations: [
        GruposTrabajoUsuariosSubirComponent,
        GruposTrabajoUsuariosListarComponent,
        GruposTrabajoUsuariosGestionarComponent
    ],
    imports: [
        GruposTrabajoUsuariosRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatOptionModule,
        NgSelectModule,
        MatTooltipModule,
        MatDialogModule,
        MatCheckboxModule,
        MatAutocompleteModule,
        LogErroresModule,
        CargasMasivasModule,
        OpenTrackingModule
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class GruposTrabajoUsuariosModule {}
