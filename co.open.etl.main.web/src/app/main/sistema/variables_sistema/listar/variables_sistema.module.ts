import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {MatTooltipModule} from '@angular/material/tooltip';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {AuthGuard} from 'app/auth.guard';
import {VariablesSistemaComponent} from './variables_sistema.component';
import {VariablesSistemaGestionarModule} from '../gestionar/variables_sistema_gestionar.module';
import {OpenTrackingModule} from '../../../commons/open-tracking/open-tracking.module';
import { VariablesSistemaRoutingModule } from './variables_sistema.routing';

@NgModule({
    declarations: [
        VariablesSistemaComponent,
    ],
    imports: [
        VariablesSistemaRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatTooltipModule,
        VariablesSistemaGestionarModule,
        OpenTrackingModule
    ],
    providers: [
        AuthGuard
    ]
})

export class VariablesSistemaModule {
}
