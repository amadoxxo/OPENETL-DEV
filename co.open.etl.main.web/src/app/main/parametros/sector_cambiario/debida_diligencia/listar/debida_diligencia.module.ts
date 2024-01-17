import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatTooltipModule} from '@angular/material/tooltip';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {AuthGuard} from 'app/auth.guard';
import {DebidaDiligenciaGestionarModule} from '../gestionar/debida_diligencia_gestionar.module';
import {OpenTrackingModule} from '../../../../commons/open-tracking/open-tracking.module';
import {DebidaDiligenciaComponent} from './debida_diligencia.component';
import {DebidaDiligenciaRoutingModule} from './debida_diligencia.routing';

@NgModule({
    declarations: [
        DebidaDiligenciaComponent
    ],
    imports: [
        DebidaDiligenciaRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatTooltipModule,
        MatDialogModule,
        NgxDatatableModule,
        DebidaDiligenciaGestionarModule,
        OpenTrackingModule
    ],
    providers: [
        AuthGuard
    ]
})

export class DebidaDiligenciaModule {}
