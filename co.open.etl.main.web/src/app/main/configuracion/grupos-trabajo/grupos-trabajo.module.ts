import { NgModule } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { FuseSharedModule } from '@fuse/shared.module';
import { GruposTrabajoRoutingModule } from './grupos-trabajo.routing';
import { CommonsService } from '../../../services/commons/commons.service';
import { AuthGuard } from '../../../auth.guard';

@NgModule({
    imports: [
        GruposTrabajoRoutingModule,
        TranslateModule,
        FuseSharedModule
    ],
    providers: [
        AuthGuard,
        CommonsService
    ],
})

export class GruposTrabajoModule {}
