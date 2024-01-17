import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {AlertaSagrilaftComponent} from './alerta-sagrilaft.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';

@NgModule({
    declarations: [AlertaSagrilaftComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule
    ],
    exports: [AlertaSagrilaftComponent],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
})
export class AlertaSangrilaftModule {}
