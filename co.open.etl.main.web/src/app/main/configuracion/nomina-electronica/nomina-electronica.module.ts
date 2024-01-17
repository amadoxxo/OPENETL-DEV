import { NgModule }  from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { FuseSharedModule } from '@fuse/shared.module';
import { NominaElectronicaRoutingModule } from './nomina-electronica.routing';

@NgModule({
    imports: [
        NominaElectronicaRoutingModule,
        TranslateModule,
        FuseSharedModule
    ]
})

export class NominaElectronicaModule {}
