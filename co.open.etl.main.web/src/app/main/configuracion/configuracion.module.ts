import {NgModule} from '@angular/core';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {ConfiguracionRoutingModule} from './configuracion.routing';
// Services
import {ConfiguracionService} from '../../services/configuracion/configuracion.service';
import { OpenTrackingModule } from '../commons/open-tracking/open-tracking.module';
import { ReportesBackgroundService } from '../../services/reportes/reportes_background.service';

@NgModule({
    imports: [
        ConfiguracionRoutingModule,
        TranslateModule,
        FuseSharedModule,
        OpenTrackingModule
    ],
    providers: [
        ConfiguracionService,
        ReportesBackgroundService,
    ],
    exports: []
})

export class ConfiguracionModule {}
