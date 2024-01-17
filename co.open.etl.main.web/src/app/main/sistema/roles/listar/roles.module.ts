import {NgModule} from '@angular/core';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {MatTooltipModule} from '@angular/material/tooltip';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {AuthGuard} from 'app/auth.guard';
import {RolesComponent} from './roles.component';
import {OpenTrackingModule} from '../../../commons/open-tracking/open-tracking.module';
import {RolesRoutingModule} from './roles.routing';
import {RolesGestionarModule} from '../gestionar/roles_gestionar.module';


@NgModule({
    declarations: [
        RolesComponent
    ],
    imports: [
        RolesRoutingModule,
        RolesGestionarModule,
        FuseSharedModule,
        TranslateModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatTooltipModule,
        OpenTrackingModule
    ],
    providers: [
        AuthGuard
    ]
})

export class RolesModule {
}

