import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatTooltipModule} from '@angular/material/tooltip';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {AuthGuard} from 'app/auth.guard';
import {MandatosProfesionalComponent} from './mandatos_profesional.component';
import {MandatoProfesionalGestionarModule} from '../gestionar/mandatos_profesional_gestionar.module';
import {OpenTrackingModule} from '../../../../commons/open-tracking/open-tracking.module';
import {MandatosProfesionalRoutingModule} from './mandatos_profesional.routing';

@NgModule({
    declarations: [
        MandatosProfesionalComponent,
    ],
    imports: [
        MandatosProfesionalRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatTooltipModule,
        MatDialogModule,
        NgxDatatableModule,
        MandatoProfesionalGestionarModule,
        OpenTrackingModule
    ],
    providers: [
        AuthGuard
        
    ]
})

export class MandatosProfesionalModule {}
