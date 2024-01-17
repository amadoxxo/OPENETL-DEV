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
import { LoaderModule } from 'app/shared/loader/loader.module';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { LogErroresModule } from '../../../commons/log-errores/log-errores.module';
import { OpenTrackingModule } from '../../../commons/open-tracking/open-tracking.module';
import { CargasMasivasModule } from '../../../commons/cargas-masivas/cargas-masivas.module';

import { GruposTrabajoProveedoresListarComponent } from './listar/grupos-trabajo-proveedores-listar.component';
import { GruposTrabajoProveedoresSubirComponent } from './subir/grupos-trabajo-proveedores-subir.component';
import { GruposTrabajoProveedoresGestionarComponent } from './gestionar/grupos-trabajo-proveedores-gestionar.component';
import { GruposTrabajoProveedoresRoutingModule } from './grupos-trabajo-proveedores.routing';


@NgModule({
    declarations: [
        GruposTrabajoProveedoresSubirComponent,
        GruposTrabajoProveedoresListarComponent,
        GruposTrabajoProveedoresGestionarComponent
    ],
    imports: [
        GruposTrabajoProveedoresRoutingModule,
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
        MatAutocompleteModule,
        LogErroresModule,
        CargasMasivasModule,
        OpenTrackingModule
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class GruposTrabajoProveedoresModule {}
