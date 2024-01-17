import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatTooltipModule} from '@angular/material/tooltip';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {LoaderModule} from 'app/shared/loader/loader.module';

import {AuthGuard} from 'app/auth.guard';
import {CausalesDevolucionComponent} from './causales-devolucion.component';
import {CausalesDevolucionGestionarModule} from '../gestionar/causales-devolucion-gestionar.module';
import {OpenTrackingModule} from '../../../commons/open-tracking/open-tracking.module';
import {CausalesDevolucionRoutingModule} from './causales-devolucion.routing';
import {ConfiguracionService} from '../../../../services/proyectos-especiales/recepcion/emssanar/configuracion.service';

@NgModule({
    declarations: [
        CausalesDevolucionComponent,
    ],
    imports: [
        CausalesDevolucionRoutingModule,
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatTooltipModule,
        MatDialogModule,
        NgxDatatableModule,
        CausalesDevolucionGestionarModule,
        OpenTrackingModule
    ],
    providers: [
        AuthGuard,
        ConfiguracionService
        
    ]
})

export class CausalesDevolucionModule {}
