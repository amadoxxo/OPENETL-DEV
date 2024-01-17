import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatTooltipModule} from '@angular/material/tooltip';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {AuthGuard} from 'app/auth.guard';
import {CentrosOperacionComponent} from './centros-operacion.component';
import {CentrosOperacionGestionarModule} from '../gestionar/centros-operacion-gestionar.module';
import {OpenTrackingModule} from '../../../commons/open-tracking/open-tracking.module';
import {CentrosOperacionRoutingModule} from './centros-operacion.routing';
import {ConfiguracionService} from '../../../../services/proyectos-especiales/recepcion/emssanar/configuracion.service';

@NgModule({
    declarations: [
        CentrosOperacionComponent,
    ],
    imports: [
        CentrosOperacionRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatTooltipModule,
        MatDialogModule,
        NgxDatatableModule,
        CentrosOperacionGestionarModule,
        OpenTrackingModule
    ],
    providers: [
        AuthGuard,
        ConfiguracionService
        
    ]
})

export class CentrosOperacionModule {}
