import {NgModule} from '@angular/core';
import {MatIconModule} from '@angular/material/icon';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatButtonModule} from '@angular/material/button';
import {MatTooltipModule} from '@angular/material/tooltip';

import {AuthGuard} from 'app/auth.guard';
import {LoaderModule} from 'app/shared/loader/loader.module';
import {TiemposAceptacionTacitaComponent} from './tiempos_aceptacion_tacita.component';
import {TiemposAceptacionTacitaGestionarModule} from '../gestionar/tiempos_aceptacion_tacita_gestionar.module';
import {OpenTrackingModule} from '../../../commons/open-tracking/open-tracking.module';
import { TiemposAceptacionTacitaRoutingModule } from './tiempos_aceptacion_tacita.routing';


@NgModule({
    declarations: [
        TiemposAceptacionTacitaComponent,
    ],
    imports: [
        TiemposAceptacionTacitaRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatTooltipModule,
        OpenTrackingModule,
        TiemposAceptacionTacitaGestionarModule
    ],
    providers: [
        AuthGuard
    ]
})

export class TiemposAceptacionTacitaModule {}
