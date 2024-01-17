import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {AuthGuard} from 'app/auth.guard';
import {FestivosComponent} from './festivos.component';
import {FestivosGestionarModule} from '../gestionar/festivos_gestionar.module';
import {OpenTrackingModule} from '../../../commons/open-tracking/open-tracking.module';
import { FestivosRoutingModule } from './festivos.routing';

@NgModule({
    declarations: [
        FestivosComponent,
    ],
    imports: [
        FestivosRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        FestivosGestionarModule,
        OpenTrackingModule
    ],
    providers: [
        AuthGuard
    ]
})

export class FestivosModule {
}
