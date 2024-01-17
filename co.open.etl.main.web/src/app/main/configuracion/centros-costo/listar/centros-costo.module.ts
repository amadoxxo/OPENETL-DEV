import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatTooltipModule} from '@angular/material/tooltip';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {AuthGuard} from 'app/auth.guard';
import {CentrosCostoComponent} from './centros-costo.component';
import {CentrosCostoGestionarModule} from '../gestionar/centros-costo-gestionar.module';
import {OpenTrackingModule} from '../../../commons/open-tracking/open-tracking.module';
import {CentrosCostoRoutingModule} from './centros-costo.routing';
import {ConfiguracionService} from '../../../../services/proyectos-especiales/recepcion/emssanar/configuracion.service';

@NgModule({
    declarations: [
        CentrosCostoComponent,
    ],
    imports: [
        CentrosCostoRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatTooltipModule,
        MatDialogModule,
        NgxDatatableModule,
        CentrosCostoGestionarModule,
        OpenTrackingModule
    ],
    providers: [
        AuthGuard,
        ConfiguracionService
        
    ]
})

export class CentrosCostoModule {}
