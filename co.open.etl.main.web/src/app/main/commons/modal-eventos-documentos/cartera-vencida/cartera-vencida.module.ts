import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {CarteraVencidaComponent} from './cartera-vencida.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';

@NgModule({
    declarations: [CarteraVencidaComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule
    ],
    exports: [CarteraVencidaComponent],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
})
export class CarteraVencidaModule {}

